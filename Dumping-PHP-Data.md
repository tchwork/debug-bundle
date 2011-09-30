==================================================================
Convention pour représenter avec fidélité une variable PHP en JSON
==================================================================

Introduction
============

`print_r()`, `var_dump()`, `var_export()`, `json_encode()`, `serialize()`.
Toutes ces fonctions permettent de représenter une variable PHP sous forme de
chaîne de caractères, chacune permettant d'obtenir une représentation adaptée au
besoin du moment :

* être lisible par un humain,
* être lisible par un programme,
* être fidèle dans le cas des variables complexes (récursives, objets ou
  ressources par ex.).

Pour les besoins d'un débogage, la représentation préférée doit évidement
être lisible par un humain et rester la plus fidèle possible.

Pendant le développement, il est courant en PHP d'afficher les erreurs et les
variables intermédiaires au beau milieu de la page sur laquelle on travaille.
Pourtant, cette pratique n'est pas recommandée, car elle peut casser le flux de
sortie de l'application. Dans le cas des pages HTML simples, c'est généralement
acceptable, mais dès que les pages deviennent plus complexes, que PHP est
utilisé pour générer d'autres contenus (Javascript, PDF, ZIP, etc.), cette
méthode n'est plus adaptée.

Si l'humain est toujours le lecteur final, un système de debug performant a
donc besoin d'une représentation intermédiaire pour transmettre l'état d'une
variable au système qui l'affichera dans une fenêtre dédiée.

Les types de variables PHP
==========================

Les variables en PHP peuvent prendre de nombreux types différents, qu'il faut
tous prendre en compte pour représenter avec fidélité n'importe quelle variable.

Scalaires
---------

Les scalaires rassemblent les nombres entiers, les nombres à virgule flottante,
les constantes spéciales `true`, `false`, `null`, `NAN`, `INF` et `-INF`, et
bien sûr les chaînes de caractères.

Les chaînes de caractères en PHP sont de simples séquences d'octets qui peuvent
contenir des informations éventuellement binaires, même si UTF-8 est très
souvent utilisé de nos jours pour représenter du texte.

Tableaux
--------

Les tableaux PHP sont en réalité des tables de hachages ordonnées. Ils accèptent
n'importe quelle chaîne de caractères ou entier numérique en guise de clef. Dans
le cas des clef, PHP ne fait pas la différence entre un entier numérique et sa
représentation en base 10 sous forme de chaîne de caractères.

Objets
------

Les objets sont basés sur la même structure de table de hashage ordonnée que les
tableaux à une exception près : le nom d'une propriété d'objet ne peut pas
commencer par le caractère NUL ("\x00"). Chaque objet possède également une
classe principale ainsi que des propriétés associées à une visibilité publique,
protégée ou privée.

Contrairement aux scalaires et tableaux, les objets sont passés "par référence".
[Voir le manuel PHP pour plus de précisions](http://php.net/language.oop5.references.php).

Ressources
----------

Les ressource en PHP ont un type retourné par `get_resource_type()`. Certains
types possèdent des propriétés internes qu'il est possible de consulter
avec des fonctions adéquates. Par exemple, il est possible d'obtenir plus
d'informations sur les ressources de type `stream` en appelant la fonction
`stream_get_meta_data()`, de même avec `proc_get_status()` pour les ressources
de type `process`. Les ressources possèdent également un identifiant interne
auquel on accède en transformant une ressource en chaîne de caractères.
Par exemple : `echo (string) opendir('.');` va afficher `Resource id #2`, où
`2` est l'identifiant interne de la ressource renvoyée par `opendir()`.

Les ressources sont donc très similaires aux objets PHP : comme eux, elles sont
passées "par référence", possèdent un type, et des propriétés.

Références
----------

PHP possède deux mécanismes de gestion de références :

* l'un permet de lier deux variables entre elles pour les rendre alias l'une de
  l'autre, comme dans `$b =& $a;` par exemple,
* l'autre est utilisé pour la transmission des objets et des ressources.

Les références par alias permettent de créer des structures récursives infinies,
comme dans exemple dans `$a = array(); $a[0] =& $a;`.

Elles permettent également de créer des alias internes à des positions qui ne
créent pas nécessairement de récursivité, comme par exemple dans ce code où
$b[0] et $b[1] sont liés par référence : `$a = 123; $b = array(&$a, &$a);`

Les références utilisées pour la transmission des objects/ressources permettent
de mettre le même objet/ressource en plusieurs endroits d'une structure
arborescente.

La représentation recherchée se doit de refléter la présence de ces deux types
de références, sans quoi il serait impossible de représenter une structure
récursive sans tomber dans le piège de la récursivité infinie, d'autre part
cela permet l'examen des liens internes d'une structure arborescente.

Recherche de la représentation idéale
=====================================

La représentation intermédiaire d'une variable à déboger doit :

* être aussi fidèle que possible pour permettre un débogage efficace,
* être interopérable, en particulier avec le programme en charge de la
  représenter visuellement,
* si possible rester lisible par un humain, pour faciliter le débogage du
  système débogage lui-même.

Par ailleurs, le code qui génère cette représentation intermédiaire doit être
aussi neutre que possible du point de vue de l'application dans laquelle il
s'exécute :

* il doit pouvoir être opérant quel que soit le contexte d'exécution et la
  variable à représenter,
* il doit être rapide et avoir une emprunte mémoire minimale.

Analyse des fonctions existantes
--------------------------------

Sur le seul critère d'être opérant quel que soit le contexte d'exécution, seul
`json_encode` n'est pas disqualifiée :

* `print_r` et jusqu'à PHP 5.3.3 `var_export` génèrent une erreur fatale
  lorsqu'elles sont utilisées dans le contexte d'un gestionnaire de flux de
  sortie,
* `var_dump` ne fonctionne pas dans le contexte d'un gestionnaire de flux de
  sortie,
* `serialize` ne fonctionne pas avec certains objets natifs ou autres qui
  génèrent une exception lorsqu'ils sont sérialisés.

Sur le plan de l'interopérabilité :

* les sorties de `print_r` et `var_dump` sont prévues pour être lues par un
  humain, pas particulièrement par un programme,
* `var_export` génère une représentation sous forme de code PHP, ce qui reste
  lisible pour un humain mais n'est facilement lu que par PHP lui-même,
* la sortie de `serialize` est prévue pour être lue par la fonction
  `unserialize` native à PHP, quasiment illisible pour un humain,
* `json_encode` génère une sortie interopérable, éventuellement lisible par un
  humain, même si les caractères encodés gênent la lecture.

Sur le plan de la rapidité et de l'emprunte mémoire, toutes ces fonctions
sont équivalentes.

Sur les autres critères :

* pour les structures récursives comportant des références internes,
  `serialize` les gère parfaitement, `var_export` génère une erreur fatale,
  `var_dump` et `print_r` affichent un laconique `*RECURSION*`, `json_encode`
  émet un warning et place un `null` à la place de chaque référence récursive,
* pour les variables de type `resources`, seule `var_dump` et `print_r` donnent
  une information utile,
* `json_encode` ne gère que les chaînes de caractères encodées en UTF-8, là où
  les chaînes PHP n'ont pas d'encodage particulier et peuvent même être
  binaires,
* Xdebug améliore significativement `var_dump` mais n'apporte de solution ni
  pour les gestionnaires de flux de sortie ni pour l'interopérabilité.

Ainsi, aucune fonction native ne combine les qualités fondamentales recherchées.

Convention JSON détaillée
=========================

Pour le critère de lisibilité et surtout d'interopérabilité, le format JSON
semble le plus adapté.

Sans autre convention, JSON ne suffit pas nativement à représenter toute
l'étendue des valeurs que peut prendre une variable PHP.

Pour contrôler la performance et l'emprunte mémoire, il est souhaitable
également de pouvoir restreindre l'exhaustivité de la représentation, en
limitant par exemple les tableaux à leurs premiers éléments, les chaînes de
caractères à leurs premiers octets et les structures arborescentes à un niveau
de profondeur maximal.

La représentation décrite dans la suite établit des conventions qui permettent
d'utiliser JSON pour décrire toutes ces possibilités.

Chaînes de caractères
---------------------

JSON ne permet de représenter que des chaînes de caractères UTF-8. Une
chaîne de caractères PHP arbitraire est préparée ainsi :

Si la chaîne ``$str`` considérée n'est pas valide en UTF-8, alors elle est
transformée en UTF-8 grâce à ``"b`". utf8_encode($str)``. Toute chaîne de
caractères déjà valide en UTF-8 est conservée identique à elle-même, sauf si
elle contient un backtick, auquel cas elle est préfixée par `` u` ``. Si une
limite de longueur est applicable, la chaîne de caractères est tronquée selon
cette limite. La longueur initiale décomptée en nombre de caractères UTF-8 est
alors préfixée à la chaîne. Le prefix `` u` `` est dans ce cas obligatoire même
si la chaîne d'origine ne contient pas de backtick. Ensuite la chaîne de
caractères résultante est encodée en JSON natif.

Par exemple : `"\xA9"` devient ``"b`©"``, ``"a`b"`` devient ``"u`a`b"`` et
`"©"` reste sous cette forme. La chaîne UTF-8 de longueur quatre ``"aébà"``
tronquée à deux caractères est représentée sous la forme ``"4u`aé"``. La chaîne
de caractères vide est toujours représentée ainsi `""`.

Cette convention de représentation laisse de la place pour d'autres préfix
que `` b` `` ou `` u` ``. Ainsi les préfix `` r` ``, `` R` ``, `` f` `` sont
utilisés pour représenter des valeurs spéciales (cf. suite).

Nombres et autres scalaires
---------------------------

Les nombres entiers, flottants ou les valeurs `true`, `false` et `null` sont
représentés nativement en JSON.

Les constantes spéciales `NAN`, `INF` et `-INF` sont représentées par des
chaînes de caractères JSON, respectivement :
``"f`NAN"``, ``"f`INF"`` et ``"f`-INF"``.

Dans le contexte des clefs d'une structure associative cependant, JSON n'accèpte
que des chaînes de caractères. Les clefs numériques des tableaux PHP sont donc
représentées sous la forme de chaînes de caractères JSON. Comme PHP ne fait
aucune distinction entre les clefs numériques représentées sous forme de chaîne
de caractères ou d'entier, ceci n'impacte pas la fidélité de la représentation.

Structures associatives : tableaux, objets et ressources
--------------------------------------------------------

Les tableaux vides sont représentés sous la forme JSON ``[]``.

Les tableaux, objets et ressources sont représentés par la syntaxe objet de
JSON, selon les règles suivantes :

* les clefs `"_"`, `"__maxLength"`, `"__maxDepth"`, `"__refs"` et `"__proto__"`
  sont réservées,
* les clefs correspondant à des propriétés protégées d'objets sont préfixées
  par `*:`
* les clefs correspondant à des propriétés privées d'objets sont préfixées par
  le nom de la classe qui leur est associée suivie d'un `:`,
* les autres clefs sont préfixées par un `:` lorsqu'elles entrent en collision
  avec une clef réservée ou qu'elles contiennent un `:`.

Les clefs réservées ont une sémantique définie ainsi :

* `"_"` contient le numéro d'ordre dans la structure associative générale
  représentée, suivie d'un `:`, puis :
  * pour les objets du nom de leur classe,
  * pour les tableaux du mot-clef `array` suivi d'un `:` puis de leur longueur
    retournée par `count($tableau)`,
  * pour les ressources du mot-clef `resource` suivi d'un `:` puis de leur type
    retourné par `get_resource_type($resource)`.
* `"__maxLength"` contient le nombre d'éléments tronqués lorsqu'une limite
  de nombre est applicable,
* `"__maxDepth"` est présent lorsque la structure locale est à un niveau de
  profondeur supérieur à la limite applicable, et contient le nombre d'éléments
  tronqués ; lorsque cette clef est présente, l'objet JSON local ne contient
  ainsi que deux clefs : `"_"` et `"__maxDepth"`,
* `"__refs"` contient une table des références internes de la structure
  générale représentée (cf. suite), elle ne devrait donc être présente qu'au
  niveau de profondeur le plus bas, en dernière position,
* `"__proto__"` n'a pas de sémantique particulière mais est réservé pour
  assurer la compatibilité du JSON produit avec certains navigateurs.

Références internes
-------------------

À l'intérieur des structures arborescentes, on identifie chaque position au
moyen d'un numéro correspondant à son ordre de découverte dans la structure,
selon un algorithme de parcours en profondeur tenant compte des éventuelles
références internes.

TO BE CONTINUED...

Exemples
========

TO BE CONTINUED...

Implémentation
==============

[Patchwork's JsonDumper](https://gist.github.com/1069975#file_json_dumper.php).

