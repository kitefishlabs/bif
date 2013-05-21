<?php
require_once 'init.php';
require_once 'util.php';
require_once '../bif.php';
requireLogin();
connectDB();
bifPageHeader('change password');
if ((array_key_exists('changepasswordError',$_SESSION)) && ($_SESSION['changepasswordError'] != ''))
    {
    echo '<div style="background:#ff8080">' . $_SESSION['changepasswordError'] . '</div>';
    unset($_SESSION['changepasswordError']);
    }
?>
<form method="POST" action="api.php">
<input type="hidden" name="command" value="updatePassword">
<input type="hidden" name="returnurl" value=".">
<table cellpadding="3">
<tr>
<th>Old password</th>
<td><input type="password" name="oldpassword" /></td>
</tr>
<tr>
<th>New password</th>
<td><input type="password" name="newpassword1" /></td>
</tr>
<tr>
<th>Confirm new password</th>
<td><input type="password" name="newpassword2" /></td>
</tr>
</table>
<input type="submit" name="submit" value="Submit" />
</form>
</div>

<?php
bifPageFooter();
?>