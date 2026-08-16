<section class="toolbar">
    <input type="search" id="account-search" placeholder="Recherche…" autocomplete="off">
    <select id="filter-group">
        <option value="">Tous les groupes</option>
        <option>PRINCIPAUX</option>
        <option>PROMOTION</option>
        <option>SECONDAIRES</option>
        <option>TESTS</option>
        <option>AUTRES</option>
    </select>
    <select id="filter-status">
        <option value="">Tous les statuts</option>
        <option value="idle">IDLE</option>
        <option value="busy">BUSY</option>
        <option value="connected">CONNECTÉ</option>
        <option value="session_expired">SESSION EXPIRÉE</option>
        <option value="disabled">DISABLED</option>
        <option value="error">ERROR</option>
    </select>
    <select id="sort-by">
        <option value="label">Tri : nom</option>
        <option value="username">Tri : username</option>
        <option value="group">Tri : groupe</option>
        <option value="status">Tri : statut</option>
        <option value="favorite">Tri : favoris</option>
    </select>
    <label class="chip"><input type="checkbox" id="filter-fav"> Favoris</label>
    <button class="btn" type="button" id="add-account">+ AJOUTER UN COMPTE</button>
    <button class="btn secondary" type="button" id="sim-fanout" hidden>Simuler NEW_POST</button>
</section>
<div id="account-grid" class="account-grid"></div>
<p class="muted" id="empty-accounts" hidden>Aucun compte. Ajoutez-en un — aucune limite logicielle.</p>

<dialog id="modal-account">
    <form method="dialog" class="modal-form" id="form-account">
        <h2>Ajouter un compte</h2>
        <p class="muted">Aucun mot de passe TikTok n’est stocké. La session vit dans un profil navigateur isolé.</p>
        <label>Libellé<input name="label" required placeholder="Account 01"></label>
        <label>Username TikTok<input name="username" placeholder="@account01"></label>
        <label>Nom d’affichage<input name="display_name"></label>
        <label>Groupe
            <select name="group">
                <option>PRINCIPAUX</option>
                <option>PROMOTION</option>
                <option>SECONDAIRES</option>
                <option>TESTS</option>
                <option selected>AUTRES</option>
            </select>
        </label>
        <menu>
            <button value="cancel" class="btn secondary">Annuler</button>
            <button value="ok" class="btn" id="save-account">Enregistrer</button>
        </menu>
    </form>
</dialog>

<dialog id="modal-target">
    <form method="dialog" class="modal-form" id="form-target">
        <h2>Ajouter une cible</h2>
        <input type="hidden" name="account_id">
        <label>Nom<input name="name" required placeholder="Artist A"></label>
        <label>Username<input name="username" required placeholder="@artist_a"></label>
        <menu>
            <button value="cancel" class="btn secondary">Annuler</button>
            <button value="ok" class="btn" id="save-target">Associer</button>
        </menu>
    </form>
</dialog>

<dialog id="modal-scenario">
    <form method="dialog" class="modal-form" id="form-scenario">
        <h2>Créer un scénario</h2>
        <input type="hidden" name="account_id">
        <label>Cible
            <select name="artist_id" id="scenario-artist"></select>
        </label>
        <label>Libellé<input name="label" value="Scenario 01"></label>
        <label>Déclencheur
            <select name="trigger">
                <option value="NEW_POST">NEW_POST</option>
                <option value="SCHEDULED_SCENARIO">SCHEDULED_SCENARIO</option>
                <option value="USER_ACTION">USER_ACTION</option>
            </select>
        </label>
        <label>Temporisation
            <select name="timing_type" id="timing-type">
                <option value="fixed">Fixe (secondes)</option>
                <option value="minutes">Minutes</option>
                <option value="hours">Heures</option>
                <option value="window">Fenêtre (0 → N secondes)</option>
                <option value="random_window">Aléatoire dans une fenêtre</option>
            </select>
        </label>
        <label>Valeur<input type="number" min="0" name="timing_value" value="5"></label>
        <div id="scenario-steps" class="step-editor"></div>
        <button type="button" class="btn secondary" id="add-step">+ AJOUTER UNE ÉTAPE</button>
        <button type="button" class="btn secondary" id="record-scenario">ENREGISTRER UN SCÉNARIO</button>
        <menu>
            <button value="cancel" class="btn secondary">Annuler</button>
            <button value="ok" class="btn" id="save-scenario">Créer</button>
        </menu>
    </form>
</dialog>

<dialog id="modal-publish">
    <form method="dialog" class="modal-form" id="form-publish">
        <h2>Publications</h2>
        <input type="hidden" name="account_id">
        <label>Publier avec<input name="account_label" readonly></label>
        <label>Caption<textarea name="caption" rows="4" placeholder="Texte… tapez @ pour mentionner"></textarea></label>
        <div class="mention-box" id="mention-box" hidden></div>
        <div class="dropzone" id="dropzone">Glissez des médias ici ou cliquez
            <input type="file" id="media-input" accept="image/*,video/*" multiple hidden>
        </div>
        <div class="media-previews" id="media-previews"></div>
        <label class="chip"><input type="radio" name="mode" value="now" checked> PUBLIER MAINTENANT</label>
        <label class="chip"><input type="radio" name="mode" value="scheduled"> PROGRAMMER</label>
        <label>Date / heure<input type="datetime-local" name="scheduled_at"></label>
        <menu>
            <button value="cancel" class="btn secondary">Annuler</button>
            <button value="ok" class="btn" id="save-publish">Planifier</button>
        </menu>
    </form>
</dialog>

<dialog id="modal-settings">
    <form method="dialog" class="modal-form" id="form-settings">
        <h2>Paramètres du compte</h2>
        <input type="hidden" name="account_id">
        <label>Libellé<input name="label"></label>
        <label>Username<input name="username"></label>
        <label>Groupe<select name="group">
            <option>PRINCIPAUX</option><option>PROMOTION</option>
            <option>SECONDAIRES</option><option>TESTS</option><option>AUTRES</option>
        </select></label>
        <menu>
            <button value="cancel" class="btn secondary">Fermer</button>
            <button value="ok" class="btn" id="save-settings">Enregistrer</button>
        </menu>
    </form>
</dialog>
