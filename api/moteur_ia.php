<?php
declare(strict_types=1);
require_once __DIR__ . '/outils_donnees.php';

/**
 * Moteur NLU maison de l'assistant AB ENGINS.
 *
 * Pipeline : question → normalisation → tokenisation + racinisation (stemming)
 *          → vectorisation TF-IDF → similarité cosinus avec chaque intention
 *          → extraction d'entités (durées, statuts, noms de clients)
 *          → exécution de l'outil SQL correspondant → réponse en français.
 *
 * Aucune dépendance externe : tout le traitement est implémenté ici.
 */

// ---------------------------------------------------------------------------
// 1. Normalisation et tokenisation
// ---------------------------------------------------------------------------

/** Minuscules + suppression des accents. */
function mia_normaliser(string $texte): string {
    $texte = mb_strtolower($texte, 'UTF-8');
    $accents = [
        'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe',
    ];
    return strtr($texte, $accents);
}

/** Mots vides français ignorés par la vectorisation. */
const MIA_STOPWORDS = ['le','la','les','un','une','des','de','du','d','l','au','aux','a','et','ou','en','dans','sur','pour','par','avec','sans','que','qui','quoi','quel','quelle','quels','quelles','est','sont','il','elle','on','nous','vous','ils','elles','ce','cette','ces','mon','ma','mes','notre','nos','moi','je','tu','se','sa','son','ses','ne','pas','plus','y','donne','donnez','moi','me','liste','listez','affiche','affichez','montre','montrez','combien','avons','ont','tous','toutes','tout','toute'];

/**
 * Racinisation légère du français : suppression itérative des suffixes
 * courants pour que « expirent », « expiration » et « expirer »
 * partagent la même racine « expir ».
 */
function mia_stem(string $mot): string {
    $suffixes = ['issements','issement','atrices','ateurs','ations','ation','emment','ements','ement','euses','euse','istes','iste','ances','ance','ences','ence','ives','ive','ants','ant','ents','ent','ees','ee','er','es','e','s','x'];
    $continuer = true;
    while ($continuer) {
        $continuer = false;
        foreach ($suffixes as $suf) {
            $len = strlen($suf);
            if (strlen($mot) - $len >= 3 && substr($mot, -$len) === $suf) {
                $mot = substr($mot, 0, -$len);
                $continuer = true;
                break;
            }
        }
    }
    return $mot;
}

/** Texte → liste de racines significatives. */
function mia_tokens(string $texte): array {
    $mots = preg_split('/[^a-z0-9]+/', mia_normaliser($texte), -1, PREG_SPLIT_NO_EMPTY);
    $tokens = [];
    foreach ($mots as $m) {
        if (in_array($m, MIA_STOPWORDS, true) || strlen($m) < 2) {
            continue;
        }
        $tokens[] = ctype_digit($m) ? $m : mia_stem($m);
    }
    return $tokens;
}

// ---------------------------------------------------------------------------
// 2. Intentions : chaque intention est décrite par un « document » de
//    mots-clés et de formulations types (le corpus du TF-IDF).
// ---------------------------------------------------------------------------

function mia_intentions(): array {
    return [
        'salutation' => "bonjour salut bonsoir hello coucou merci ca va aide aider peux tu faire capable questions poser",
        'stats' => "resume synthese bilan statistiques vue ensemble activite globale parc tableau bord indicateurs chiffres situation generale entreprise resumer",
        'permis' => "permis exploitation expirent expiration expire renouveler renouvellement echeance valide suspendu forestier foret superficie region delivrance",
        'factures' => "factures facture impayees impayee impaye paiement paye payer regler reglement retard montant total du dette doit reste payer solde tva facturation",
        'contrats' => "contrats contrat location louer signes signature actifs duree effet fin prevue reconduction resilie termine renouvele montant",
        'engins' => "engins engin machines machine bulldozer pelle grue niveleuse chargeuse camion disponibles disponible disponibilite loue maintenance etat defectueux panne parc materiel code modele serie",
        'interventions' => "interventions intervention assistance depannage panne probleme reparation technicien resolu resolution attente motif sav maintenance urgente",
        'clients' => "clients client coordonnees telephone email contact representant secteur societe entreprise partenaires liste annuaire",
    ];
}

// ---------------------------------------------------------------------------
// 3. Vectorisation TF-IDF + similarité cosinus
// ---------------------------------------------------------------------------

/** Vecteur TF (fréquence des termes) d'une liste de tokens. */
function mia_tf(array $tokens): array {
    $tf = [];
    foreach ($tokens as $t) {
        $tf[$t] = ($tf[$t] ?? 0) + 1;
    }
    return $tf;
}

/** IDF lissé calculé sur le corpus des intentions. */
function mia_idf(array $corpusTokens): array {
    $n = count($corpusTokens);
    $df = [];
    foreach ($corpusTokens as $tokens) {
        foreach (array_unique($tokens) as $t) {
            $df[$t] = ($df[$t] ?? 0) + 1;
        }
    }
    $idf = [];
    foreach ($df as $t => $d) {
        $idf[$t] = log(($n + 1) / ($d + 1)) + 1.0;
    }
    return $idf;
}

/** Similarité cosinus entre deux vecteurs creux. */
function mia_cosinus(array $a, array $b): float {
    $dot = 0.0; $na = 0.0; $nb = 0.0;
    foreach ($a as $t => $v) {
        $na += $v * $v;
        if (isset($b[$t])) { $dot += $v * $b[$t]; }
    }
    foreach ($b as $v) { $nb += $v * $v; }
    return ($na > 0 && $nb > 0) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
}

/**
 * Détecte l'intention la plus probable d'une question.
 * Retourne [intention, score] ; score < seuil → intention nulle (fallback).
 */
function mia_detecter(string $question): array {
    $corpus = [];
    foreach (mia_intentions() as $intent => $doc) {
        $corpus[$intent] = mia_tokens($doc);
    }
    $idf = mia_idf($corpus);
    $idfInconnu = log((count($corpus) + 1) / 1) + 1.0;

    $vecteurs = [];
    foreach ($corpus as $intent => $tokens) {
        $v = [];
        foreach (mia_tf($tokens) as $t => $freq) {
            $v[$t] = $freq * $idf[$t];
        }
        $vecteurs[$intent] = $v;
    }

    $vq = [];
    foreach (mia_tf(mia_tokens($question)) as $t => $freq) {
        $vq[$t] = $freq * ($idf[$t] ?? $idfInconnu);
    }

    $meilleur = null; $meilleurScore = 0.0;
    foreach ($vecteurs as $intent => $v) {
        $score = mia_cosinus($vq, $v);
        if ($score > $meilleurScore) { $meilleurScore = $score; $meilleur = $intent; }
    }
    return $meilleurScore >= 0.12 ? [$meilleur, $meilleurScore] : [null, $meilleurScore];
}

// ---------------------------------------------------------------------------
// 4. Extraction d'entités (durées, statuts, nom de client)
// ---------------------------------------------------------------------------

function mia_entites(string $question): array {
    $q = mia_normaliser($question);
    $e = ['jours' => null, 'statut' => null, 'client' => null];

    // Durées : « 60 jours », « ce mois », « cette semaine », « ce trimestre »...
    if (preg_match('/(\d+)\s*(?:prochains?\s*)?jours?/', $q, $m)) {
        $e['jours'] = (int) $m[1];
    } elseif (preg_match('/(\d+)\s*mois/', $q, $m)) {
        $e['jours'] = 30 * (int) $m[1];
    } elseif (preg_match('/\b(ce|le|du)\s+mois\b|\bmois\s+prochain\b/', $q)) {
        $e['jours'] = 30;
    } elseif (preg_match('/\bsemaine\b/', $q)) {
        $e['jours'] = 7;
    } elseif (preg_match('/\btrimestre\b/', $q)) {
        $e['jours'] = 90;
    } elseif (preg_match('/\bannee\b|\ban\b/', $q)) {
        $e['jours'] = 365;
    }

    // Statuts (mot → valeur ENUM en base), testés du plus spécifique au plus général.
    $statuts = [
        'en retard' => 'en_retard', 'retard' => 'en_retard',
        'impay' => 'impaye', 'partiel' => 'partiel', 'paye' => 'paye', 'regle' => 'paye',
        'suspendu' => 'suspendu', 'expire' => 'expire', 'valide' => 'valide',
        'disponible' => 'disponible', 'maintenance' => 'maintenance', 'loue' => 'loue',
        'defectueu' => 'defectueux', 'panne' => 'defectueux',
        'attente' => 'en_attente', 'en cours' => 'en_cours', 'resolu' => 'resolu',
        'actif' => 'actif', 'termine' => 'termine', 'resilie' => 'resilie', 'renouvele' => 'renouvele',
    ];
    foreach ($statuts as $mot => $valeur) {
        if (strpos($q, $mot) !== false) { $e['statut'] = $valeur; break; }
    }

    // Nom de client : comparaison de la question avec les noms en base.
    try {
        $noms = db()->query('SELECT nom_client FROM client')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($noms as $nom) {
            $nomNorm = mia_normaliser($nom);
            if (strpos($q, $nomNorm) !== false) { $e['client'] = $nom; break; }
            // Sinon : un mot significatif du nom (≥ 4 lettres) présent dans la question.
            foreach (preg_split('/[^a-z0-9]+/', $nomNorm, -1, PREG_SPLIT_NO_EMPTY) as $mot) {
                if (strlen($mot) >= 4 && preg_match('/\b' . preg_quote($mot, '/') . '\b/', $q)) {
                    $e['client'] = $nom;
                    break 2;
                }
            }
        }
    } catch (Throwable $ignore) {
        // Base indisponible : on continue sans entité client.
    }

    return $e;
}

// ---------------------------------------------------------------------------
// 5. Génération des réponses
// ---------------------------------------------------------------------------

function mia_fcfa(float $montant): string {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

function mia_date(string $date): string {
    return (new DateTime($date))->format('d/m/Y');
}

/** Limite une liste à $max lignes et ajoute « … et N autres ». */
function mia_lignes(array $lignes, int $max = 10): string {
    $extra = count($lignes) - $max;
    $vue = array_slice($lignes, 0, $max);
    $texte = implode("\n", $vue);
    if ($extra > 0) {
        $texte .= "\n- … et {$extra} autre(s)";
    }
    return $texte;
}

const MIA_AIDE = "Je peux répondre à des questions sur :\n"
    . "- **Permis** — « Quels permis expirent dans les 60 prochains jours ? »\n"
    . "- **Factures** — « Quelles factures sont impayées ? »\n"
    . "- **Contrats** — « Liste des contrats actifs »\n"
    . "- **Engins** — « Quels engins sont disponibles ? »\n"
    . "- **Interventions** — « Quelles interventions sont en attente ? »\n"
    . "- **Clients** — « Coordonnées du client Kamdem »\n"
    . "- **Résumé** — « Donne-moi un résumé de l'activité du parc »";

/**
 * Point d'entrée : question en français → réponse en français.
 * $historique : questions précédentes de la conversation (pour le suivi de contexte).
 */
function moteurRepondre(string $question, array $historique = []): string {
    [$intent, $score] = mia_detecter($question);
    $e = mia_entites($question);

    // Désambiguïsation : « combien nous doit le client X ? » mentionne un client
    // mais porte sur l'argent → on bascule vers l'intention factures.
    if ($intent === 'clients' && preg_match('/doit|dette|montant|impay|solde|facture|paye/', mia_normaliser($question))) {
        $intent = 'factures';
    }

    // Suivi de contexte (anaphore) : « et ses contrats ? », « combien doit-il ? »
    // → si la question fait référence à un client sans le nommer, on reprend
    //   le dernier client mentionné dans l'historique de la conversation.
    $qn = mia_normaliser($question);
    if ($e['client'] === null
        && preg_match('/\b(ses|son|sa|lui|il|elle|leur|celui|celle|ce client|meme client|encore)\b|^\s*et\s/', $qn)) {
        foreach (array_reverse($historique) as $precedente) {
            $ePrec = mia_entites($precedente);
            if ($ePrec['client'] !== null) {
                $e['client'] = $ePrec['client'];
                break;
            }
        }
    }

    switch ($intent) {
        case 'salutation':
            return "Bonjour ! Je suis l'assistant AB ENGINS.\n" . MIA_AIDE;

        case 'stats': {
            $s = runTool('stats_generales', []);
            return "**Situation du parc au " . date('d/m/Y') . " :**\n"
                . "- Contrats actifs : **{$s['contrats_actifs']}**\n"
                . "- Engins disponibles : **{$s['engins_disponibles']}** sur {$s['engins_total']}\n"
                . "- Permis expirant sous 30 jours : **{$s['permis_expirant_30j']}**\n"
                . "- Total impayé : **" . mia_fcfa((float) $s['total_impaye_fcfa']) . "**";
        }

        case 'permis': {
            $input = [];
            if (in_array($e['statut'], ['valide', 'expire', 'suspendu'], true)) {
                $input['statut'] = $e['statut'];
            }
            // « expirent bientôt / à renouveler » sans durée précisée → 60 jours par défaut.
            $qn = mia_normaliser($question);
            if ($e['jours'] !== null) {
                $input['expire_avant_jours'] = $e['jours'];
                $input['statut'] = 'valide';
            } elseif (empty($input['statut']) && preg_match('/expir|renouvel|echeance|bientot/', $qn)) {
                $input['expire_avant_jours'] = 60;
                $input['statut'] = 'valide';
            }
            $rows = runTool('lister_permis', $input);
            if ($rows === []) {
                return isset($input['expire_avant_jours'])
                    ? "Aucun permis n'expire dans les {$input['expire_avant_jours']} prochains jours."
                    : "Aucun permis ne correspond à ce critère.";
            }
            $lignes = array_map(fn($p) => "- **{$p['numero_permis']}** ({$p['nom_client']}, {$p['region']}) — expire le "
                . mia_date($p['date_expiration'])
                . ($p['jours_restants'] >= 0 ? " (dans {$p['jours_restants']} j)" : '')
                . " — statut : {$p['statut']}", $rows);
            $titre = isset($input['expire_avant_jours'])
                ? count($rows) . " permis expire(nt) dans les {$input['expire_avant_jours']} prochains jours :"
                : count($rows) . " permis trouvé(s) :";
            return "**{$titre}**\n" . mia_lignes($lignes);
        }

        case 'factures': {
            $input = [];
            if (in_array($e['statut'], ['paye', 'partiel', 'en_retard', 'impaye'], true)) {
                $input['statut_paiement'] = $e['statut'];
            }
            if ($e['client'] !== null) {
                $input['client'] = $e['client'];
            }
            $rows = runTool('lister_factures', $input);
            // « impayées / dues / reste à payer » sans statut exact → tout ce qui n'est pas soldé.
            $qn = mia_normaliser($question);
            if (empty($input['statut_paiement']) && preg_match('/impay|du\b|dette|reste|doit|solde/', $qn)) {
                $rows = array_values(array_filter($rows, fn($f) => (float) $f['reste_a_payer'] > 0));
            }
            if ($rows === []) {
                return "Aucune facture ne correspond à ce critère" . ($e['client'] ? " pour {$e['client']}" : '') . '.';
            }
            $total = array_sum(array_map(fn($f) => (float) $f['reste_a_payer'], $rows));
            $lignes = array_map(fn($f) => "- **{$f['numero_facture']}** ({$f['nom_client']}) — "
                . mia_fcfa((float) $f['montant_ttc']) . " TTC, reste à payer : **" . mia_fcfa((float) $f['reste_a_payer'])
                . "** ({$f['statut_paiement']})", $rows);
            return '**' . count($rows) . ' facture(s)' . ($e['client'] ? " pour {$e['client']}" : '')
                . ', reste à encaisser : ' . mia_fcfa($total) . "**\n" . mia_lignes($lignes);
        }

        case 'contrats': {
            $input = [];
            if (in_array($e['statut'], ['actif', 'termine', 'resilie', 'renouvele'], true)) {
                $input['statut'] = $e['statut'];
            }
            if ($e['client'] !== null) {
                $input['client'] = $e['client'];
            }
            $rows = runTool('lister_contrats', $input);
            if ($rows === []) {
                return "Aucun contrat ne correspond à ce critère.";
            }
            $lignes = array_map(fn($c) => "- **CT-" . substr($c['date_effet'], 0, 4) . '-' . sprintf('%03d', (int) $c['id_contrat'])
                . "** ({$c['nom_client']}) — " . ($c['engins'] ?: 'aucun engin') . " — du " . mia_date($c['date_effet'])
                . " au " . mia_date($c['date_fin_prevue']) . " — " . mia_fcfa((float) $c['montant_ht'])
                . " HT ({$c['statut_contrat']})", $rows);
            return '**' . count($rows) . ' contrat(s) trouvé(s) :**' . "\n" . mia_lignes($lignes);
        }

        case 'engins': {
            $input = [];
            if (in_array($e['statut'], ['disponible', 'loue', 'maintenance'], true)) {
                $input['disponibilite'] = $e['statut'];
            } elseif ($e['statut'] === 'defectueux') {
                $input['etat'] = 'defectueux';
            }
            $rows = runTool('lister_engins', $input);
            if ($rows === []) {
                return "Aucun engin ne correspond à ce critère.";
            }
            $lignes = array_map(fn($en) => "- **{$en['code_engin']}** — {$en['type_engin']} " . ($en['modele_engin'] ?? '')
                . " — état : {$en['etat_engin']}, {$en['disponibilite']}", $rows);
            return '**' . count($rows) . ' engin(s) trouvé(s) :**' . "\n" . mia_lignes($lignes);
        }

        case 'interventions': {
            $input = [];
            if (in_array($e['statut'], ['en_attente', 'en_cours', 'resolu'], true)) {
                $input['statut'] = $e['statut'];
            }
            $rows = runTool('lister_interventions', $input);
            if ($rows === []) {
                return "Aucune intervention ne correspond à ce critère.";
            }
            $lignes = array_map(fn($i) => "- " . mia_date($i['date_intervention']) . " — **{$i['nom_client']}** ({$i['engin']}) — "
                . ($i['motif_intervention'] ?: 'motif non précisé') . " — technicien : {$i['technicien']} ({$i['statut_intervention']})", $rows);
            return '**' . count($rows) . ' intervention(s) trouvée(s) :**' . "\n" . mia_lignes($lignes);
        }

        case 'clients': {
            $input = [];
            if ($e['client'] !== null) {
                $input['recherche'] = $e['client'];
            }
            $rows = runTool('lister_clients', $input);
            if ($rows === []) {
                return "Aucun client ne correspond à cette recherche.";
            }
            $lignes = array_map(fn($c) => "- **{$c['nom_client']}** ({$c['libelle_secteur']}) — représentant : {$c['nom_representant']}"
                . ($c['telephone_client'] ? ", tél. {$c['telephone_client']}" : '')
                . ($c['email_client'] ? ", {$c['email_client']}" : ''), $rows);
            return '**' . count($rows) . ' client(s) :**' . "\n" . mia_lignes($lignes);
        }

        default:
            return "Je n'ai pas compris la question.\n" . MIA_AIDE;
    }
}
