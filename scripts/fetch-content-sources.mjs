import { mkdir, readFile, readdir, rm } from "node:fs/promises";
import { resolve } from "node:path";
import { spawnSync } from "node:child_process";

const outputRoot = resolve(process.argv[2] ?? ".content");
const packagePattern = /^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/;
const repositoryPattern = /^https:\/\/github\.com\/[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\.git$/;

function clone(repository, destination, reference = null) {
    const arguments_ = ["-c", "advice.detachedHead=false", "clone", "--quiet", "--depth=1", "--filter=blob:none", "--single-branch"];
    if (reference === null) {
        arguments_.push("--no-tags");
    } else {
        arguments_.push("--branch", reference);
    }
    arguments_.push(repository, destination);
    const result = spawnSync(
        "git",
        arguments_,
        { stdio: "inherit" },
    );
    if (result.status !== 0) {
        throw new Error(`Unable to fetch canonical content from ${repository}`);
    }
}

function latestVersionTag(repository) {
    const result = spawnSync("git", ["ls-remote", "--tags", "--refs", repository, "refs/tags/v*"], {
        encoding: "utf8",
    });
    if (result.status !== 0) throw new Error(`Unable to list published versions from ${repository}`);

    const versions = result.stdout
        .split("\n")
        .map((line) => line.match(/refs\/tags\/(v(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?)$/))
        .filter((match) => match !== null)
        .map((match) => ({
            tag: match[1],
            major: Number(match[2]),
            minor: Number(match[3]),
            patch: Number(match[4]),
            prerelease: match[5] ?? null,
        }));
    versions.sort((left, right) =>
        left.major - right.major
        || left.minor - right.minor
        || left.patch - right.patch
        || (left.prerelease === null ? 1 : right.prerelease === null ? -1 : left.prerelease.localeCompare(right.prerelease, "en")),
    );
    const latest = versions.at(-1);
    if (latest === undefined) throw new Error(`Canonical repository ${repository} has no published semantic version`);

    return latest.tag;
}

async function registrations(root) {
    const entries = await readdir(root, { withFileTypes: true });
    const packages = [];
    for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name, "en"))) {
        if (!entry.isFile() || !entry.name.endsWith(".json")) {
            throw new Error(`Unexpected registry entry '${entry.name}'`);
        }

        const name = entry.name.slice(0, -5);
        const registration = JSON.parse(await readFile(resolve(root, entry.name), "utf8"));
        if (!packagePattern.test(name) || registration.name !== name) {
            throw new Error(`Registry entry '${entry.name}' has an invalid package name`);
        }
        if (!repositoryPattern.test(registration.repository)) {
            throw new Error(`Registry entry '${entry.name}' has an invalid canonical repository`);
        }
        packages.push(registration);
    }

    if (packages.length === 0) throw new Error("The registry contains no package");
    return packages;
}

await rm(outputRoot, { recursive: true, force: true });
await mkdir(outputRoot, { recursive: true });

const silexRepository = "https://github.com/Matanek/Silex.git";
const silexVersion = latestVersionTag(silexRepository);
clone(silexRepository, resolve(outputRoot, "Silex"), silexVersion);
clone("https://github.com/Matanek/Silex-Registry.git", resolve(outputRoot, "Silex-Registry"));

const packageRoot = resolve(outputRoot, "Packages");
await mkdir(packageRoot, { recursive: true });
const registryRoot = resolve(outputRoot, "Silex-Registry/registry/v1/packages");
const packages = await registrations(registryRoot);
for (const registration of packages) {
    const version = latestVersionTag(registration.repository);
    clone(registration.repository, resolve(packageRoot, registration.name), version);
}

console.log(`Fetched Silex ${silexVersion}, the registry, and ${packages.length} published package versions`);
