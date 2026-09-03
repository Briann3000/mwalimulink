<?php
// logout.php
auth_logout();
header('Location: index.php?action=landing');
exit();
?>
