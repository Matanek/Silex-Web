import silexGrammar from "silex:textmate-grammar";
import { createCssVariablesTheme, createHighlighterCore } from "@shikijs/core";
import { createJavaScriptRegexEngine } from "@shikijs/engine-javascript";

const codeBlocks = Array.from(document.querySelectorAll(
    ".prose-silex pre > code.language-sx, .prose-silex pre > code.language-silex",
));

if (codeBlocks.length > 0) {
    highlightSilexCode(codeBlocks).catch((error) => {
        // Plain CommonMark code remains readable when highlighting is unavailable.
        console.warn("Silex syntax highlighting is unavailable.", error);
    });
}

async function highlightSilexCode(blocks) {
    const language = {
        ...silexGrammar,
        name: "silex",
        aliases: ["sx"],
    };
    const theme = createCssVariablesTheme({
        name: "silex-css-variables",
        variablePrefix: "--sx-",
        fontStyle: true,
    });
    const highlighter = await createHighlighterCore({
        langs: [language],
        themes: [theme],
        engine: createJavaScriptRegexEngine(),
    });

    for (const block of blocks) {
        const source = (block.textContent ?? "").replace(/\r?\n$/, "");
        const rendered = highlighter.codeToHtml(source, {
            lang: "silex",
            theme: "silex-css-variables",
        });
        const template = document.createElement("template");
        template.innerHTML = rendered;
        const highlightedPre = template.content.querySelector("pre");
        const highlightedCode = highlightedPre?.querySelector("code");
        const pre = block.parentElement;

        if (highlightedPre === null || highlightedCode === null || pre === null) {
            continue;
        }

        block.replaceChildren(...highlightedCode.childNodes);
        block.dataset.silexHighlighted = "true";
        pre.classList.add("shiki", "silex-css-variables");
        pre.style.backgroundColor = "var(--sx-background)";
        pre.style.color = "var(--sx-foreground)";
        pre.tabIndex = 0;
    }

    highlighter.dispose();
}
