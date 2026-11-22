<?php
require_once 'config.php';
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}
$db = getDB();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    try {
        if ($action === 'delete_idea') {
            $stmt = $db->prepare("SELECT image FROM ideas WHERE id = ?");
            $stmt->execute([$id]);
            $idea = $stmt->fetch();
            if ($idea && $idea['image']) {
                $imagePath = UPLOAD_DIR . $idea['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $stmt = $db->prepare("SELECT image FROM responses WHERE idea_id = ?");
            $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $response) {
                if ($response['image']) {
                    $imagePath = UPLOAD_DIR . $response['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
            }
            $stmt = $db->prepare("DELETE FROM ideas WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Идея успешно удалена';
        } elseif ($action === 'delete_response') {
            $stmt = $db->prepare("SELECT image FROM responses WHERE id = ?");
            $stmt->execute([$id]);
            $response = $stmt->fetch();
            if ($response && $response['image']) {
                $imagePath = UPLOAD_DIR . $response['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $stmt = $db->prepare("DELETE FROM responses WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Ответ успешно удалён';
        } elseif ($action === 'delete_user') {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$id]);
            $message = 'Пользователь успешно удалён';
        }
    } catch (Exception $e) {
        $error = 'Ошибка: ' . $e->getMessage();
    }
}
$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM ideas) as total_ideas,
        (SELECT COUNT(*) FROM responses) as total_responses
")->fetch();
$ideas = $db->query("
    SELECT i.*, u.username,
        (SELECT COUNT(*) FROM responses WHERE idea_id = i.id) as response_count
    FROM ideas i
    JOIN users u ON i.user_id = u.id
    ORDER BY i.created_at DESC
")->fetchAll();
$users = $db->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM ideas WHERE user_id = u.id) as ideas_count,
        (SELECT COUNT(*) FROM responses WHERE user_id = u.id) as responses_count
    FROM users u
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - Платформа идей</title>
    <?php include 'includes_head.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>⚙️ Админ панель</h1>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_ideas'] ?></div>
                <div class="stat-label">Идей</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_responses'] ?></div>
                <div class="stat-label">Ответов</div>
            </div>
        </div>
        <div class="admin-section">
            <h2>📝 Управление идеями</h2>
            <?php if (empty($ideas)): ?>
                <p>Нет идей</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Автор</th>
                            <th>Ответов</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ideas as $idea): ?>
                            <tr>
                                <td><?= $idea['id'] ?></td>
                                <td>
                                    <a href="idea.php?id=<?= $idea['id'] ?>" target="_blank">
                                        <?= h(mb_substr($idea['title'], 0, 50)) ?>
                                    </a>
                                </td>
                                <td><?= h($idea['username']) ?></td>
                                <td><?= $idea['response_count'] ?></td>
                                <td><?= date('d.m.Y', strtotime($idea['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Вы уверены, что хотите удалить эту идею?');">
                                        <input type="hidden" name="action" value="delete_idea">
                                        <input type="hidden" name="id" value="<?= $idea['id'] ?>">
                                        <button type="submit" class="btn-delete">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="admin-section">
            <h2>👥 Управление пользователями</h2>
            <?php if (empty($users)): ?>
                <p>Нет пользователей</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Никнейм</th>
                            <th>Идей</th>
                            <th>Ответов</th>
                            <th>Админ</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= h($user['username']) ?></td>
                                <td><?= $user['ideas_count'] ?></td>
                                <td><?= $user['responses_count'] ?></td>
                                <td><?= $user['is_admin'] ? '✅' : '—' ?></td>
                                <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <?php if (!$user['is_admin']): ?>
                                        <form method="POST" style="display: inline;"
                                              onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn-delete">Удалить</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>