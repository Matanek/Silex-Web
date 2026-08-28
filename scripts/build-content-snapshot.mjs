import { copyFile, mkdir, readFile, readdir, rename, rm, stat, writeFile } from "node:fs/promises";
import { createHash } from "node:crypto";
import { dirname, join, resolve } from "node:path";
import { spawnSync } from "node:child_process";

const documentationRoot = resolve(process.argv[2] ?? "../Silex-Documentation");
const registryRoot = resolve(process.argv[3] ?? "../Silex-Registry");
const silexRoot = resolve(process.argv[4] ?? "../Silex");
const packagesRoot = resolve(process.argv[5] ?? "../Packages");
const outputRoot = resolve(process.argv[6] ?? "var/content/sources");
const stagingRoot = `${outputRoot}.tmp-${process.pid}`;
const packagePattern = /^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/;
const repositoryPattern = /^https:\/\/github\.com\/[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\.git$/;

async function exists(path) {
    try {
        await stat(path);
        return true;
    } catch {
        return false;
    }
}

async function readJson(path, label) {
    try {
        return JSON.parse(await readFile(path, "utf8"));
    } catch (error) {
        throw new Error(`${label} is not valid JSON: ${error.message}`);
    }
}

async function copyMarkdownTree(source, destination) {
    if (!(await exists(source))) return 0;

    let count = 0;
    for (const entry of await readdir(source, { withFileTypes: true })) {
        const sourcePath = join(source, entry.name);
        const destinationPath = join(destination, entry.name);
        if (entry.isSymbolicLink()) throw new Error(`Documentation symlink is not supported: ${sourcePath}`);
        if (entry.isDirectory()) {
            count += await copyMarkdownTree(sourcePath, destinationPath);
        } else if (entry.isFile() && entry.name.endsWith(".md")) {
            await mkdir(dirname(destinationPath), { recursive: true });
            await copyFile(sourcePath, destinationPath);
            count += 1;
        }
    }

    return count;
}

function gitValue(root, arguments_) {
    const result = spawnSync("git", ["-C", root, ...arguments_], { encoding: "utf8" });
    return result.status === 0 ? result.stdout.trim() : null;
}

const registryPackagesRoot = join(registryRoot, "registry/v1/packages");
const registrationEntries = await readdir(registryPackagesRoot, { withFileTypes: true });
const registrations = [];
for (const entry of registrationEntries.sort((left, right) => left.name.localeCompare(right.name, "en"))) {
    if (!entry.isFile() || !entry.name.endsWith(".json")) {
        throw new Error(`Unexpected registry entry '${entry.name}'`);
    }

    const name = entry.name.slice(0, -5);
    const source = join(registryPackagesRoot, entry.name);
    const registration = await readJson(source, entry.name);
    if (registration.schema !== 1 || !packagePattern.test(name) || registration.name !== name) {
        throw new Error(`Registry entry '${entry.name}' has an invalid package contract`);
    }
    if (!repositoryPattern.test(registration.repository)) {
        throw new Error(`Registry entry '${entry.name}' has an invalid canonical repository`);
    }
    registrations.push({ name, repository: registration.repository, source });
}
if (registrations.length === 0) throw new Error("The registry contains no package");

await rm(stagingRoot, { recursive: true, force: true });
await mkdir(stagingRoot, { recursive: true });

try {
    const documentCounts = {};
    for (const language of ["EN", "FR"]) {
        const count = await copyMarkdownTree(join(documentationRoot, language), join(stagingRoot, "Silex-Documentation", language));
        if (count === 0) throw new Error(`The canonical ${language} documentation contains no Markdown file`);
        documentCounts[language.toLowerCase()] = count;
    }
    if (documentCounts.en !== documentCounts.fr) {
        throw new Error(`The EN and FR documentation inventories differ (${documentCounts.en} vs ${documentCounts.fr})`);
    }

    for (const registration of registrations) {
        const registrationDestination = join(stagingRoot, "Silex-Registry/registry/v1/packages", `${registration.name}.json`);
        await mkdir(dirname(registrationDestination), { recursive: true });
        await copyFile(registration.source, registrationDestination);
    }

    const packageMetadata = [];
    for (const registration of registrations) {
        const manifestSource = join(packagesRoot, registration.name, "Package.json");
        const manifest = await readJson(manifestSource, `${registration.name} package manifest`);
        const description = typeof manifest.description === "string" ? manifest.description.trim() : "";
        if (manifest.name !== registration.name || description === "") {
            throw new Error(`Package manifest '${manifestSource}' has an invalid name or description`);
        }
        if (typeof manifest.version !== "string" || !/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(manifest.version)) {
            throw new Error(`Package manifest '${manifestSource}' has an invalid version`);
        }

        const manifestDestination = join(stagingRoot, "Packages", registration.name, "Package.json");
        await mkdir(dirname(manifestDestination), { recursive: true });
        await copyFile(manifestSource, manifestDestination);
        packageMetadata.push({ name: registration.name, version: manifest.version, description });
    }
    const packageMetadataDigest = createHash("sha256")
        .update(JSON.stringify(packageMetadata))
        .digest("hex");

    const toolchainManifest = await readFile(join(silexRoot, "Toolchain/build.zig.zon"), "utf8");
    const version = toolchainManifest.match(/\.version\s*=\s*"([^"]+)"/)?.[1];
    if (!version || !/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(version)) {
        throw new Error("The canonical Silex version is missing or invalid");
    }

    await writeFile(
        join(stagingRoot, "snapshot.json"),
        `${JSON.stringify({
            schema: 2,
            silex: { version, commit: gitValue(silexRoot, ["rev-parse", "HEAD"]), tag: gitValue(silexRoot, ["describe", "--tags", "--exact-match"]) },
            documentation: {
                commit: gitValue(documentationRoot, ["rev-parse", "HEAD"]),
                reference: gitValue(documentationRoot, ["branch", "--show-current"]),
                documents: documentCounts,
            },
            registry: { commit: gitValue(registryRoot, ["rev-parse", "HEAD"]), packages: registrations.length },
            packages: { manifests: packageMetadata.length, digest: packageMetadataDigest },
        }, null, 2)}\n`,
    );

    await rm(outputRoot, { recursive: true, force: true });
    await mkdir(dirname(outputRoot), { recursive: true });
    await rename(stagingRoot, outputRoot);
    await writeFile(join(dirname(outputRoot), "silex-version.txt"), `${version}\n`);
    console.log(`Built Silex ${version} content: ${documentCounts.en} mirrored documents and ${packageMetadata.length} package descriptions`);
} catch (error) {
    await rm(stagingRoot, { recursive: true, force: true });
    throw error;
}
