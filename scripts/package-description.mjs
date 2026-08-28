const languageTagPattern = /^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/;

function validLine(value) {
    return typeof value === "string"
        && value.length > 0
        && value.trim() === value
        && !value.includes("\n")
        && !value.includes("\r");
}

export function normalizePackageDescription(value) {
    if (validLine(value)) return value;
    if (value === null || typeof value !== "object" || Array.isArray(value)) return null;

    const translations = Object.entries(value);
    if (translations.length === 0) return null;

    const languages = new Set();
    let hasEnglish = false;
    for (const [language, text] of translations) {
        const normalizedLanguage = language.toLowerCase();
        if (!languageTagPattern.test(language) || languages.has(normalizedLanguage) || !validLine(text)) {
            return null;
        }
        languages.add(normalizedLanguage);
        hasEnglish ||= normalizedLanguage === "en";
    }
    if (!hasEnglish) return null;

    return Object.fromEntries(translations.sort(([left], [right]) =>
        left.localeCompare(right, "en", { sensitivity: "base" })));
}
