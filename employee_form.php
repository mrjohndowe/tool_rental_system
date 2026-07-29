<?php
require __DIR__ . '/includes/header.php';
$pdo=db(); $id=(int)($_GET['id'] ?? $_POST['id'] ?? 0); $returnTo=$_GET['return_to'] ?? $_POST['return_to'] ?? '';
$employee=['name'=>trim($_GET['prefill'] ?? ''),'work_email'=>'','badge_number'=>'','active'=>1];
if ($id) { $s=$pdo->prepare('SELECT * FROM employees WHERE id=?'); $s->execute([$id]); $employee=$s->fetch() ?: $employee; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  $name=trim($_POST['name']??''); $email=trim($_POST['work_email']??''); $badge=trim($_POST['badge_number']??''); $active=isset($_POST['active'])?1:0;
  if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('A name and valid work email are required.');
  if ($id) { $s=$pdo->prepare('UPDATE employees SET name=?,work_email=?,badge_number=?,active=? WHERE id=?'); $s->execute([$name,$email,$badge?:null,$active,$id]); $newId=$id; }
  else { $s=$pdo->prepare('INSERT INTO employees(name,work_email,badge_number,active) VALUES(?,?,?,?)'); $s->execute([$name,$email,$badge?:null,$active]); $newId=(int)$pdo->lastInsertId(); }
  flash('success','Employee saved.');
  redirect($returnTo==='checkout' ? 'checkout.php?employee_id='.$newId : 'employees.php');
 } catch (Throwable $e) { flash('error', str_contains($e->getMessage(),'Duplicate') ? 'That email or badge number is already in use.' : $e->getMessage()); redirect('employee_form.php'.($id?'?id='.$id:'')); }
}
?>
<div class="card"><h2><?= $id ? 'Edit' : 'Add' ?> Employee</h2><form method="post">
<input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
<label>Full Name</label><input name="name" required value="<?= e($employee['name']) ?>">
<label>Work Email</label><input type="email" name="work_email" required value="<?= e($employee['work_email']) ?>">
<label>Badge Number <span class="muted">(optional)</span></label><input name="badge_number" value="<?= e($employee['badge_number']) ?>">
<label><input type="checkbox" name="active" value="1" style="width:auto" <?= $employee['active'] ? 'checked' : '' ?>> Active employee</label>
<div class="actions"><button>Save Employee</button><a class="button secondary" href="<?= $returnTo==='checkout'?'checkout.php':'employees.php' ?>">Cancel</a></div>
</form></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
