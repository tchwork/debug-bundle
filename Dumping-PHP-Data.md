===============================================================
Utiliser JSON pour représenter de façon fidèle une variable PHP
===============================================================

Introduction
============

* `print_r()`
* `var_dump()`
* `var_export()`
* `json_encode()`
* `serialize()`

Toutes ces fonctions permettent de représenter une variable PHP sous forme de chaîne de caractères,
chacune permettant d'obtenir une représentation adaptée au besoin du moment :

* être lisible par un humain,
* être lisible par un programme,
* être fidèle dans le cas des variables complexes (récursives, objets ou ressources par ex.).

Pour les besoins d'un debuggage, la représentation préférée doit évidement être lisible par un humain et rester la plus fidèle possible.

Pendant le développement, il est courant en PHP d'afficher les erreurs et les variables intermédiaires au beau milieu de la page sur laquelle on travaille. Pourtant, cette pratique n'est pas recommandée, car elle peut casser le flux de sortie de l'application. Dans le cas des pages HTML simples, c'est généralement acceptable, mais dès que les pages deviennent plus complexes, que PHP est utilisé pour générer d'autres contenus (Javascript, PDF, ZIP, etc.), cette méthode n'est plus adaptée.

Si l'humain est toujours le lecteur final, un système de debug performant a donc besoin d'une représentation intermédiaire pour transmettre l'état d'une variable au système qui l'affichera dans une fenêtre dédiée.

Recherche de la représentation idéale
=====================================

La représentation intermédiaire d'une variable à debugger doit :

* être aussi fidèle que possible pour permettre un debuggage efficace,
* être interopérable, en particulier avec le programme en charge de la représenter visuellement,
* si possible rester lisible par un humain, pour faciliter le debuggage du système debuggage lui-même.

Par ailleurs, le code qui génère cette représentation intermédiaire doit lui-même être aussi neutre que possible du point de vue de l'application dans laquelle il s'exécute :

* il doit pouvoir être opérant quel que soit le contexte d'exécution et la variable à représenter,
* il doit être rapide et avoir une emprunte mémoire minimale.

Analyse des fonctions existantes
--------------------------------

Sur le plan de la rapidité et de l'emprunte mémoire, toutes ces fonctions sont équivalentes.

Sur le seul critère d'être opérant quel que soit le contexte d'exécution, seul `json_encode` n'est pas disqualifiée :

* `print_r` et jusqu'à PHP 5.3.3 `var_export` génèrent une erreur fatale lorsqu'elles sont utilisées dans le contexte d'un gestionnaire de flux de sortie,
* `var_dump` ne fonctionne pas dans le contexte d'un gestionnaire de flux de sortie,
* `serialize` ne fonctionne pas avec certains objets natifs ou autres qui génèrent une exception lorsqu'ils sont sérialisés.

Sur le plan de l'intéropérabilité :

* les sorties de `print_r` et `var_dump` sont prévues pour être lues par un humain, pas particulièrement par un programme,
* `var_export` génère une représentation sous forme de code PHP, ce qui reste lisible pour un humain mais n'est facilement lu que par PHP lui-même,
* la sortie de `serialize` est prévue pour être lue par la fonction `unserialize` native à PHP, quasiment illisible pour un humain,
* `json_encode` génère une sortie intéropérable, éventuellement lisible par un humain, même si les caractères encodés gênent la lecture.

Sur les autres critères :

* pour les structures récursives comportant des références internes, `serialize` les gère parfaitement, `var_export` génère une erreur fatale, `var_dump` et `print_r` affichent un laconique `*RECURSION*`, `json_encode` émet un warning et place un `null` à la place de chaque référence récursive,
* pour les variables de type `resources`, seule `var_dump` et `print_r` donnent une information utile,
* `json_encode` ne gère que les chaînes de caractères encodées en UTF-8, là où les chaînes PHP n'ont pas d'encodage particulier et peuvent même être binaires,
* Xdebug améliore significativement `var_dump` mais ne corrige pas le problème au sein des gestionnaires de flux de sortie.

Ainsi, aucune fonction native ne combine les qualités fondamentales recherchées.

Présentation de la représentation choisie
-----------------------------------------

Pour le critère de lisibilité et surtout d'interopérabilité, le format JSON semble le plus adapté.

Sans autre convention, JSON ne suffit pas car il ne permet pas de représenter nativement toute l'étendue des valeurs que peut prendre une variable PHP :

* chaînes de caractères binaires,
* références internes, récursives ou non,
* constantes spéciales (NAN et +/-INF),
* type des objets, ainsi que visibilité des propriétés,
* type et méta-données pour les ressources.

Pour contrôler la performance et l'emprunte mémoire, il est souhaitable également de pouvoir restreindre l'exhaustivité de la représentation, en limitant par exemple les tableaux à leurs premiers éléments, les chaînes de caractères à leurs premiers octets et les structures arborescentes à un niveau de profondeur maximal.

La représentation décrite dans la suite établit des conventions qui permettent d'utiliser JSON pour décrire ces différents cas.
