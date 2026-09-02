import { access, readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { build, context } from "esbuild";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const watch = process.argv.includes("--watch");
const configuredGrammar = process.env.SILEX_TEXTMATE_GRAMMAR?.trim();
const grammarCandidates = [
    configuredGrammar,
    resolve(root, "../Silex-Extension-VSCode/syntaxes/silex.tmLanguage.json"),
    resolve(root, ".content/Silex-Extension-VSCode/syntaxes/silex.tmLanguage.json"),
].filter((candidate) => typeof candidate === "string" && candidate !== "");

let grammarPath = null;
for (const candidate of grammarCandidates) {
    try {
        await access(candidate);
        grammarPath = candidate;
        break;
    } catch {
        // Try the next canonical checkout location.
    }
}

if (grammarPath === null) {
    throw new Error(
        "Unable to find the canonical Silex TextMate grammar. Set SILEX_TEXTMATE_GRAMMAR or run npm run content:fetch.",
    );
}

const grammarSource = await readFile(grammarPath, "utf8");
const grammar = JSON.parse(grammarSource);
if (grammar.scopeName !== "source.silex" || !Array.isArray(grammar.patterns)) {
    throw new Error(`Invalid Silex TextMate grammar: ${grammarPath}`);
}

const options = {
    entryPoints: [resolve(root, "assets/js/documentation-syntax.js")],
    outfile: resolve(root, "public/assets/documentation-syntax.js"),
    bundle: true,
    format: "iife",
    minify: true,
    platform: "browser",
    target: ["es2022"],
    plugins: [{
        name: "canonical-silex-textmate-grammar",
        setup(buildContext) {
            buildContext.onResolve({ filter: /^silex:textmate-grammar$/ }, () => ({
                path: "silex:textmate-grammar",
                namespace: "silex",
            }));
            buildContext.onLoad({ filter: /.*/, namespace: "silex" }, () => ({
                contents: `export default ${JSON.stringify(grammar)};`,
                loader: "js",
            }));
        },
    }],
};

if (watch) {
    const buildContext = await context(options);
    await buildContext.watch();
    console.log(`Watching Silex syntax highlighting with ${grammarPath}`);
} else {
    await build(options);
    console.log(`Built Silex syntax highlighting with ${grammarPath}`);
}
