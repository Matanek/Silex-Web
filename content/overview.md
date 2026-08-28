# Silex documentation

This portal brings the language and package documentation together without
turning the website into a second source of truth.

The website will publish documentation from the repositories that own it:

- the language documentation remains in `Silex/Docs/`;
- each package keeps its own documentation;
- this website renders those sources without becoming a second editable copy.

## First example

```silex
func main() {
    print("Hello from Silex!")
}
```

The next step is to connect the canonical repositories and build the navigation
from their manifests. Until then, the complete language guide remains available
in the [Silex repository](https://github.com/Matanek/Silex/tree/main/Docs/Language).
