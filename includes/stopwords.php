<?php
/**
 * Mots vides (mots sans valeur pour la recherche) par langue.
 *
 * Utilises pour filtrer la question du visiteur SANS appel a l'IA : la langue de
 * l'installation WordPress choisit la liste (l'anglais est toujours ajoute).
 * Pour ajouter une langue : ajouter son code a deux lettres et sa liste ci-dessous.
 * Les mots sont en minuscules, avec leurs accents (la question est mise en minuscules
 * et la ponctuation retiree avant ce filtre).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mots vides d'une langue (code a deux lettres : en, fr, de, it, es, pt).
 *
 * @return string[]|null Liste de mots, ou null si la langue n'a pas de liste locale.
 */
function fabioai_chatbot_get_stopwords(string $lang): ?array {
    static $cache = [];

    $lang = strtolower(substr($lang, 0, 2));

    if (array_key_exists($lang, $cache)) {
        return $cache[$lang];
    }

    $lists = [
        'en' => 'a an the and or but if of to in on at by for with from as is are was were be been being am
            do does did doing have has had having will would could should can may might must shall
            i me my we our us you your he him his she her it its they them their this that these those
            there here what which who whom whose where when why how not no yes so than then too very just
            also any some all about into over under up down out off again more most other such only own same
            each both few please show tell let',

        'fr' => 'le la les l un une des du de d et ou mais si donc or ni car en dans sur sous avec sans pour par
            vers chez entre ce cet cette ces ça ca cela celui celle ceux celles je tu il elle on nous vous ils
            elles me te se lui leur leurs moi toi soi mon ma mes ton ta tes son sa ses notre nos votre vos
            est es suis sommes êtes sont été être étais était serait sera ai as avons avez ont avoir avait
            aura eu fait faire peut peux pouvez peuvent puis comment quoi quel quelle quels quelles qui que
            quand où pourquoi combien ne pas plus moins très trop aussi alors donc ainsi au aux à y
            tout tous toute toutes même autre autres quelque quelques dois doit doivent veux veut voulez
            dit dis dites merci svp',

        'de' => 'der die das den dem des ein eine einen einem einer eines und oder aber wenn weil dass ob als wie
            wo wann was wer wen wem wessen welche welcher welches welchen warum wieso weshalb wofür womit
            ich du er sie es wir ihr mich dich ihn uns euch ihnen mir dir ihm mein meine meinen dein deine
            sein seine ihre unser unsere euer eure ist sind war waren bin bist seid wird werden wurde wurden
            worden hat haben hatte hatten habe hast kann können konnte könnte muss müssen soll sollen will
            wollen darf dürfen mag mögen in im ins an am auf aus bei mit nach von vom vor zu zum zur über
            unter durch für gegen ohne um bis seit nicht kein keine keinen auch noch nur schon sehr mehr
            wenig hier dort da dann doch denn nun mal bitte danke gibt gibt es',

        'it' => 'il lo la l i gli le un uno una di a da in con su per tra fra e ed o ma se che chi cui
            come dove quando perché perche cosa quale quali quanto quanta quanti quante io tu lui lei noi
            voi loro mi ti ci vi si ne mio mia miei mie tuo tua tuoi tue suo sua suoi sue nostro nostra
            vostro vostra è e sono sei siamo siete era erano sarà essere stato stata ho hai ha abbiamo
            avete hanno avere aveva avrà può puoi possiamo possono posso devo deve dobbiamo devono voglio
            vuole vogliamo del dello della dei degli delle al allo alla ai agli alle dal dallo dalla dai
            dagli dalle nel nello nella nei negli nelle sul sullo sulla sui sugli sulle non più meno molto
            troppo anche ancora già solo questo questa questi queste quello quella quelli quelle qui qua
            lì là poi allora quindi così grazie prego per favore',

        'es' => 'el la los las lo un una unos unas de del al a en con sin por para sobre entre desde hasta hacia
            y e o u pero sino si que como cual cuales quien quienes cuyo donde dónde cuando cuándo cómo
            qué quién por qué porque cuánto cuánta cuántos cuántas yo tú vos usted él ella nosotros nosotras
            vosotros vosotras ustedes ellos ellas me te se nos os les mi mis tu tus su sus nuestro nuestra
            vuestro vuestra es son era eran fue fueron ser soy eres somos sois estar está están estaba
            estoy estás estamos ha han he has hemos habéis había haber hay tiene tienen tengo tienes
            tenemos tener puede puedo puedes podemos pueden poder debe debo debes deben quiero quiere
            queremos quieren no ni muy más menos mucho mucha muchos muchas poco también tampoco ya aún
            todavía solo sólo este esta estos estas ese esa esos esas aquel aquella aquí ahí allí
            entonces así gracias favor',

        'pt' => 'o a os as um uma uns umas de do da dos das em no na nos nas por pelo pela pelos pelas para pra
            com sem sobre entre até desde ao aos à às e ou mas porém se que como qual quais quem cujo onde
            quando porque porquê quanto quanta quantos quantas eu tu ele ela nós vós eles elas você vocês
            me te se lhe nos lhes meu minha meus minhas teu tua teus tuas seu sua seus suas nosso nossa
            é são era eram foi foram ser sou és somos estar está estão estava estou estamos há hão havia
            haver tem têm tenho tens temos ter tinha pode posso podes podemos podem poder deve devo deves
            devem quero quer queremos querem não nem muito muita muitos muitas pouco mais menos também já
            ainda só apenas este esta estes estas esse essa esses essas aquele aquela aqui aí ali lá
            então assim obrigado obrigada favor',
    ];

    if (!isset($lists[$lang])) {
        $cache[$lang] = null;

        return null;
    }

    $words = preg_split('/\s+/u', trim($lists[$lang]), -1, PREG_SPLIT_NO_EMPTY);

    // Le mot de deux lettres ou plus seulement : les mots d'une lettre sont deja ecartes.
    $cache[$lang] = array_values(array_unique($words));

    return $cache[$lang];
}
