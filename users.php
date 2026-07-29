<?php
require __DIR__ . '/includes/header.php';
require_admin();
$pdo = db();
$editId = (int)($_GET['edit'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? 'save';
        if ($action === 'toggle') {
            if ($editId === (int)current_user()['id']) throw new RuntimeException('You cannot deactivate your own account.');
            $pdo->prepare('UPDATE users SET active = IF(active=1,0,1) WHERE id=?')->execute([$editId]);
            flash('success', 'User status updated.');
        } else {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'clerk';
            if ($fullName === '' || $username === '') throw new RuntimeException('Full name and username are required.');
            if (!$editId && strlen($password) < 8) throw new RuntimeException('New passwords must be at least 8 characters.');
            if ($editId) {
                if ($password !== '' && strlen($password) < 8) throw new RuntimeException('Passwords must be at least 8 characters.');
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET full_name=?,username=?,role=?,password_hash=? WHERE id=?')->execute([$fullName,$username,$role,password_hash($password,PASSWORD_DEFAULT),$editId]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name=?,username=?,role=? WHERE id=?')->execute([$fullName,$username,$role,$editId]);
                }
                if ($editId === (int)current_user()['id']) {
                    $_SESSION['user']['full_name']=$fullName; $_SESSION['user']['username']=$username; $_SESSION['user']['role']=$role;
                }
                flash('success', 'User updated.');
            } else {
                $pdo->prepare('INSERT INTO users (full_name,username,password_hash,role) VALUES (?,?,?,?)')->execute([$fullName,$username,password_hash($password,PASSWORD_DEFAULT),$role]);
                flash('success', 'User created.');
            }
        }
        redirect('users.php');
    } catch (Throwable $e) {
        $message = str_contains($e->getMessage(), 'Duplicate') ? 'That username is already in use.' : $e->getMessage();
        flash('error', $message); redirect('users.php' . ($editId ? '?edit='.$editId : ''));
    }
}

$editing = null;
if ($editId) { $s=$pdo->prepare('SELECT id,full_name,username,role,active FROM users WHERE id=?'); $s->execute([$editId]); $editing=$s->fetch(); }
$users = $pdo->query('SELECT id,full_name,username,role,active,last_login_at,created_at FROM users ORDER BY full_name')->fetchAll();
?>
<div class="grid two"><div class="card"><h2><?= $editing?'Edit User':'Add User' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>"><label>Full Name</label><input name="full_name" required value="<?= e($editing['full_name']??'') ?>"><label>Username</label><input name="username" required autocomplete="off" value="<?= e($editing['username']??'') ?>"><label>Password <?= $editing?'<span class="muted">(leave blank to keep current password)</span>':'' ?></label><input type="password" name="password" autocomplete="new-password" <?= $editing?'':'required' ?>><label>Role</label><select name="role"><option value="clerk" <?= ($editing['role']??'')==='clerk'?'selected':'' ?>>Clerk — issue and return tools</option><option value="admin" <?= ($editing['role']??'')==='admin'?'selected':'' ?>>Administrator — includes user management</option></select><div class="actions"><button>Save User</button><?php if($editing):?><a class="button secondary" href="users.php">Cancel</a><?php endif;?></div></form></div>
<div class="card"><h2>Access Levels</h2><p><strong>Clerks</strong> can use the dashboard, check tools in and out, and manage employees and tools.</p><p><strong>Administrators</strong> have all clerk access and can create, edit, activate, and deactivate login accounts.</p></div></div>
<div class="card" style="margin-top:18px"><h2>System Users</h2><div class="table-wrap"><table><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Last Login</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?= e($u['full_name']) ?></td><td><?= e($u['username']) ?></td><td><?= e(ucfirst($u['role'])) ?></td><td><?= $u['last_login_at']?e(date('M j, Y g:i A',strtotime($u['last_login_at']))):'Never' ?></td><td><span class="badge <?= $u['active']?'available':'maintenance' ?>"><?= $u['active']?'Active':'Inactive' ?></span></td><td><div class="actions"><a class="button secondary" href="users.php?edit=<?= (int)$u['id'] ?>">Edit</a><?php if((int)$u['id']!==(int)current_user()['id']):?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="<?= $u['active']?'button danger':'button success' ?>"><?= $u['active']?'Deactivate':'Activate' ?></button></form><?php endif;?></div></td></tr><?php endforeach;?></tbody></table></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
