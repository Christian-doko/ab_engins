<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

$activeNav = 'assistant';
$pageTitle = 'Assistant IA';
$pageSubtitle = 'Interrogez vos données en langage naturel';
$pageScripts = ['js/assistant.js'];

require __DIR__ . '/partials/head.php';
?>
        <section class="card chat-card">
          <div class="chat-messages" id="chatMessages">
            <div class="chat-msg chat-msg-bot">
              <p>Bonjour ! Je suis l'assistant AB ENGINS. Posez-moi une question sur vos clients,
              contrats, engins, permis, factures ou interventions.</p>
            </div>
          </div>

          <div class="chat-suggestions" id="chatSuggestions">
            <button type="button" data-q="Quels permis expirent dans les 60 prochains jours ?">Permis à renouveler</button>
            <button type="button" data-q="Quelles factures sont impayées ou en retard, et pour quel montant ?">Factures impayées</button>
            <button type="button" data-q="Donne-moi un résumé de l'activité du parc.">Résumé du parc</button>
            <button type="button" data-q="Quelles interventions d'assistance sont encore en attente ?">Interventions en attente</button>
          </div>

          <form class="chat-input" id="chatForm">
            <input type="text" id="chatInput" placeholder="Ex. : quelles factures sont en retard chez Kamdem BTP ?" autocomplete="off" />
            <button class="btn btn-primary" type="submit" id="chatSend">
              <svg viewBox="0 0 24 24"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
              Envoyer
            </button>
          </form>
          <p class="form-error" id="chatError" hidden></p>
        </section>
<?php require __DIR__ . '/partials/foot.php'; ?>
