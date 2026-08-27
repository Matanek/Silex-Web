# Documentation Silex

Ce portail réunit la documentation du langage et des packages sans transformer
le site en une seconde source de vérité.

Le site publiera la documentation depuis les dépôts qui la possèdent :

- la documentation du langage reste dans `Silex/Docs/` ;
- chaque package conserve sa propre documentation ;
- ce site rend ces sources sans devenir une seconde copie éditable.

## Premier exemple

```silex
func main() {
    print("Bonjour depuis Silex !")
}
```

La prochaine étape consistera à connecter les dépôts canoniques et à construire
la navigation depuis leurs manifestes. En attendant, le guide complet du langage
reste disponible dans le [dépôt Silex](https://github.com/Matanek/Silex/tree/main/Docs/Language).
