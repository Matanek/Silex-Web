import { copyFile, mkdir, readFile, readdir, rename, rm, stat, writeFile } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { spawnSync } from "node:child_process";

const silexRoot = resolve(process.argv[2] ?? "../Silex");
const registryRoot = resolve(process.argv[3] ?? "../Silex-Registry");
const packagesRoot = resolve(process.argv[4] ?? "../Packages");
const outputRoot = resolve(process.argv[5] ?? "var/content/sources");
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

function gitCommit(root) {
    const result = spawnSync("git", ["-C", root, "rev-parse", "HEAD"], { encoding: "utf8" });
    return result.status === 0 ? result.stdout.trim() : null;
}

function gitTag(root) {
    const result = spawnSync("git", ["-C", root, "describe", "--tags", "--exact-match"], { encoding: "utf8" });
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
    const silexDocs = await copyMarkdownTree(join(silexRoot, "Docs"), join(stagingRoot, "Silex/Docs"));
    if (silexDocs === 0) throw new Error("The canonical Silex documentation contains no Markdown file");

    const snapshotPackages = [];
    for (const registration of registrations) {
        const packageRoot = join(packagesRoot, registration.name);
        const manifestPath = join(packageRoot, "Package.json");
        const manifest = await readJson(manifestPath, `${registration.name}/Package.json`);
        if (manifest.name !== registration.name) {
            throw new Error(`${registration.name}/Package.json does not match its registered name`);
        }
        const packageTag = gitTag(packageRoot);
        if (packageTag !== null && packageTag !== `v${manifest.version}`) {
            throw new Error(`${registration.name} tag ${packageTag} does not match Package.json version ${manifest.version}`);
        }

        const destination = join(stagingRoot, "Packages", registration.name);
        await mkdir(destination, { recursive: true });
        await copyFile(manifestPath, join(destination, "Package.json"));
        let documentCount = 0;
        if (await exists(join(packageRoot, "README.md"))) {
            await copyFile(join(packageRoot, "README.md"), join(destination, "README.md"));
            documentCount += 1;
        }
        documentCount += await copyMarkdownTree(join(packageRoot, "Docs"), join(destination, "Docs"));

        const registrationDestination = join(stagingRoot, "Silex-Registry/registry/v1/packages", `${registration.name}.json`);
        await mkdir(dirname(registrationDestination), { recursive: true });
        await copyFile(registration.source, registrationDestination);
        snapshotPackages.push({
            name: registration.name,
            repository: registration.repository,
            commit: gitCommit(packageRoot),
            tag: packageTag,
            documents: documentCount,
        });
    }

    const toolchainManifest = await readFile(join(silexRoot, "Toolchain/build.zig.zon"), "utf8");
    const version = toolchainManifest.match(/\.version\s*=\s*"([^"]+)"/)?.[1];
    if (!version || !/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/.test(version)) {
        throw new Error("The canonical Silex version is missing or invalid");
    }
    const silexTag = gitTag(silexRoot);
    if (silexTag !== null && silexTag !== `v${version}`) {
        throw new Error(`Silex tag ${silexTag} does not match toolchain version ${version}`);
    }

    await writeFile(
        join(stagingRoot, "snapshot.json"),
        `${JSON.stringify({
            schema: 1,
            silex: { version, commit: gitCommit(silexRoot), tag: silexTag, documents: silexDocs },
            registry: { commit: gitCommit(registryRoot) },
            packages: snapshotPackages,
        }, null, 2)}\n`,
    );

    await rm(outputRoot, { recursive: true, force: true });
    await mkdir(dirname(outputRoot), { recursive: true });
    await rename(stagingRoot, outputRoot);
    await writeFile(join(dirname(outputRoot), "silex-version.txt"), `${version}\n`);
    console.log(`Built Silex ${version} content: ${silexDocs} language documents and ${snapshotPackages.length} packages`);
} catch (error) {
    await rm(stagingRoot, { recursive: true, force: true });
    throw error;
}
