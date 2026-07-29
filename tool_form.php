<?php
require __DIR__.'/includes/header.php'; $pdo=db(); $id=(int)($_GET['id']??$_POST['id']??0);
$tool=['tool_name'=>'','category'=>'','manufacturer'=>'','model_number'=>'','serial_number'=>'','internal_id'=>'','tool_location'=>'','status'=>'available','notes'=>''];
if($id){$s=$pdo->prepare('SELECT * FROM tools WHERE id=?');$s->execute([$id]);$tool=$s->fetch()?:$tool;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{$vals=[trim($_POST['tool_name']??''),trim($_POST['category']??''),trim($_POST['manufacturer']??''),trim($_POST['model_number']??''),trim($_POST['serial_number']??''),trim($_POST['internal_id']??''),trim($_POST['tool_location']??''),$_POST['status']??'available',trim($_POST['notes']??'')];
 if($vals[0]===''||$vals[4]===''||$vals[5]===''||$vals[6]==='')throw new RuntimeException('Tool name, serial number, internal ID, and location are required.');
 if($id){$s=$pdo->prepare('UPDATE tools SET tool_name=?,category=?,manufacturer=?,model_number=?,serial_number=?,internal_id=?,tool_location=?,status=?,notes=? WHERE id=?');$s->execute([...$vals,$id]);}else{$s=$pdo->prepare('INSERT INTO tools(tool_name,category,manufacturer,model_number,serial_number,internal_id,tool_location,status,notes) VALUES(?,?,?,?,?,?,?,?,?)');$s->execute($vals);} flash('success','Tool saved.');redirect('tools.php');
 }catch(Throwable $e){flash('error',str_contains($e->getMessage(),'Duplicate')?'Serial number or internal ID already exists.':$e->getMessage());redirect('tool_form.php'.($id?'?id='.$id:''));}
}
?>
<div class="card"><h2><?= $id?'Edit':'Add' ?> Tool</h2><form method="post"><input type="hidden" name="id" value="<?= $id ?>">
<div class="grid two"><div><label>Tool Name</label><input name="tool_name" required value="<?= e($tool['tool_name']) ?>"><label>Category</label><input name="category" value="<?= e($tool['category']) ?>"><label>Manufacturer</label><input name="manufacturer" value="<?= e($tool['manufacturer']) ?>"><label>Model Number</label><input name="model_number" value="<?= e($tool['model_number']) ?>"></div>
<div><label>Serial Number</label><input name="serial_number" required value="<?= e($tool['serial_number']) ?>"><label>Internal ID Number</label><input name="internal_id" required value="<?= e($tool['internal_id']) ?>"><label>Tool Location</label><input name="tool_location" required value="<?= e($tool['tool_location']) ?>" placeholder="Tool crib, cabinet, shelf, truck, etc."><label>Status</label><select name="status"><?php foreach(['available','checked_out','maintenance','retired'] as $s): ?><option value="<?= $s ?>" <?= $tool['status']===$s?'selected':'' ?>><?= e(tool_status_label($s)) ?></option><?php endforeach; ?></select></div></div>
<label>Notes</label><textarea name="notes"><?= e($tool['notes']) ?></textarea><div class="actions"><button>Save Tool</button><a class="button secondary" href="tools.php">Cancel</a></div></form></div>
<?php require __DIR__.'/includes/footer.php'; ?>
