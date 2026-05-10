<?php
/**
 * Vue : liste des utilisateurs administrateurs.
 *
 * Variables :
 *   array<int, array<string,mixed>> $users       Liste des admins (id, username, created_at)
 *   string                          $siteTitle   Titre du site
 *   string                          $version     Version de l'application (pour le footer)
 */

/** @var \ICO\View\ViewRenderer $renderer */
/** @var array<int, array<string,mixed>> $users */
/** @var string $siteTitle */
/** @var string $version */
?>
<?php $renderer->renderLayout('layout/header', [
    'pageTitle' => 'Gestion des utilisateurs - ' . $siteTitle,
]); ?>
    <div class="admin-header">
        <h1>Gestion des utilisateurs</h1>
        <div class="admin-actions">
            <button onclick="openAddModal()" class="action-button action-button-success">
                Ajouter un utilisateur
            </button>
            <a href="admin.php" class="action-button action-button-secondary">Retour</a>
        </div>
    </div>

    <div class="admin-content">
        <?php $renderer->renderLayout('partials/messages'); ?>

        <div class="users-list">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Identifiant</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $isMainAdmin = $user['id'] === $users[0]['id'];
                    ?>
                    <tr class="<?php echo $isMainAdmin ? 'main-admin' : ''; ?>">
                        <td><?php echo htmlspecialchars((string) $user['id']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if ($isMainAdmin): ?>
                                <span class="admin-badge">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 2l3 7h7l-6 4 3 7-7-4-7 4 3-7-6-4h7z"/>
                                    </svg>
                                    Admin principal
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $user['created_at']); ?></td>
                        <td class="table-actions">
                            <button onclick="editUser(<?php
                                echo htmlspecialchars((string) $user['id']); ?>,
                                '<?php echo htmlspecialchars($user['username']); ?>')"
                                class="tree-button">
                                ✏️
                            </button>
                            <?php if (!$isMainAdmin): ?>
                            <button onclick="deleteUser(<?php echo htmlspecialchars((string) $user['id']); ?>)"
                                class="tree-button tree-button-danger">
                                🗑️
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal d'ajout -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h2>Ajouter un utilisateur</h2>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="username">Identifiant :</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" required minlength="12">
                    <small class="form-help">
                        Le mot de passe doit contenir au moins :
                        <ul>
                            <li>12 caractères</li>
                            <li>1 lettre minuscule</li>
                            <li>1 lettre majuscule</li>
                            <li>1 chiffre</li>
                            <li>1 caractère spécial</li>
                        </ul>
                    </small>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('addUserModal')"
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal d'édition -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <h2>Modifier l'utilisateur</h2>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label for="edit_username">Identifiant :</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Nouveau mot de passe (laisser vide pour ne pas changer) :</label>
                    <input type="password" id="edit_password" name="password" minlength="12">
                    <small class="form-help">
                        Le mot de passe doit contenir au moins :
                        <ul>
                            <li>12 caractères</li>
                            <li>1 lettre minuscule</li>
                            <li>1 lettre majuscule</li>
                            <li>1 chiffre</li>
                            <li>1 caractère spécial</li>
                        </ul>
                    </small>
                </div>
                <div class="form-actions">
                    <button type="button" onclick="closeModal('editUserModal')"
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button">Modifier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de suppression -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <h2>Confirmer la suppression</h2>
            <p>Êtes-vous sûr de vouloir supprimer cet utilisateur ?</p>
            <form method="post" action="utilisateurs.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="form-actions">
                    <button type="button" onclick="closeModal('deleteUserModal')"
                        class="action-button action-button-secondary">Annuler</button>
                    <button type="submit" class="action-button action-button-danger">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <button class="scroll-top" title="Retour en haut">↑</button>
    <script src="js/users.js"></script>
    <script src="js/scroll-top.js"></script>
<?php $renderer->renderLayout('layout/footer', ['version' => $version]); ?>
