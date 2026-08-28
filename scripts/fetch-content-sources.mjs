import { mkdir, rm } from "node:fs/promises";
import { resolve } from "node:path";
import { spawnSync } from "node:child_process";

const outputRoot = resolve(process.argv[2] ?? ".content");

function clone(repository, destination, reference = null) {
    const arguments_ = ["-c", "advice.detachedHead=false", "clone", "--quiet", "--depth=1", "--filter=blob:none", "--single-branch"];
    if (reference === null) {
        arguments_.push("--no-tags");
    } else {
        arguments_.push("--branch", reference);
    }
    arguments_.push(repository, destination);
    const result = spawnSync("git", arguments_, { stdio: "inherit" });
    if (result.status !== 0) {
        throw new Error(`Unable to fetch canonical content from ${repository}`);
    }
}

function latestVersionTag(repository) {
    const result = spawnSync("git", ["ls-remote", "--tags", "--refs", repository, "refs/tags/v*"], { encoding: "utf8" });
    if (result.status !== 0) throw new Error(`Unable to list published versions from ${repository}`);

    const versions = result.stdout
        .split("\n")
        .map((line) => line.match(/refs\/tags\/(v(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?)$/))
        .filter((match) => match !== null)
        .map((match) => ({ tag: match[1], major: Number(match[2]), minor: Number(match[3]), patch: Number(match[4]), prerelease: match[5] ?? null }));
    versions.sort((left, right) =>
        left.major - right.major
        || left.minor - right.minor
        || left.patch - right.patch
        || (left.prerelease === null ? 1 : right.prerelease === null ? -1 : left.prerelease.localeCompare(right.prerelease, "en")),
    );
    const latest = versions.at(-1);
    if (latest === undefined) throw new Error(`Canonical repository ${repository} has no published semantic version`);

    return latest;
}

await rm(outputRoot, { recursive: true, force: true });
await mkdir(outputRoot, { recursive: true });

const silexRepository = "https://github.com/Matanek/Silex.git";
const silexVersion = latestVersionTag(silexRepository);
const documentationReference = process.env.SILEX_DOCUMENTATION_REF ?? `release/${silexVersion.major}.${silexVersion.minor}`;

clone(silexRepository, resolve(outputRoot, "Silex"), silexVersion.tag);
clone("https://github.com/Matanek/Silex-Documentation.git", resolve(outputRoot, "Silex-Documentation"), documentationReference);
clone("https://github.com/Matanek/Silex-Registry.git", resolve(outputRoot, "Silex-Registry"));

console.log(`Fetched Silex ${silexVersion.tag}, documentation ${documentationReference}, and the package registry`);
