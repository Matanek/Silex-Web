# Documentation Silex

Voici le socle du nouveau portail de documentation Silex.

Le site publiera la documentation depuis les dépôts qui la possèdent :

- la documentation du langage reste dans `Silex/Docs/` ;
- chaque package conserve sa propre documentation ;
- ce site rend ces sources sans devenir une seconde copie éditable.

## Premier exemple

```silex
function main() {
    print("Bonjour depuis Silex !")
}
```

La prochaine étape consistera à connecter les dépôts canoniques et à construire la navigation depuis leurs manifestes.
