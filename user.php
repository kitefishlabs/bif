<?php
require_once 'init.php';
connectDB();
requirePrivilege(array('scheduler','organizer'));
require_once 'util.php';
require '../bif.php';

if (!isset($_GET['id']))
    die('no user id given');
else
    $user_id = $_GET['id'];

$row = dbQueryByID('select name,email,phone,snailmail from user where id=?',$user_id);
bifPageheader('user: ' . $row['name']);

if (hasPrivilege('admin'))
    {
    $stmt = dbPrepare('select privs from user where id=?');
    $stmt->bind_param('i',$user_id);
    if (!$stmt->execute())
        die($stmt->error);
    $stmt->bind_result($thisUserPrivs);
    $stmt->fetch();
    $stmt->close();
    echo <<< ENDSTRING
<div style="float:right">
<form method="POST" action="api.php">
<input type="hidden" name="privilege" value="scheduler" />
<input type="hidden" name="userid" value="$user_id" />
ENDSTRING;
    if (stripos($thisUserPrivs,'/scheduler/') !== false)
        {
        echo <<< ENDSTRING
<input type="hidden" name="command" value="removePrivilege" />
<input type="submit" name="submit" value="Remove scheduler privilege" />
ENDSTRING;
        }
    else
        {
        echo <<< ENDSTRING
<input type="hidden" name="command" value="addPrivilege" />
<input type="submit" name="submit" value="Grant scheduler privilege" />
ENDSTRING;
        }
    echo "</form>\n";
    echo <<< ENDSTRING
<form method="POST" action="api.php">
<input type="hidden" name="privilege" value="organizer" />
<input type="hidden" name="userid" value="$user_id" />
ENDSTRING;
    if (stripos($thisUserPrivs,'/organizer/') !== false)
        {
        echo <<< ENDSTRING
<input type="hidden" name="command" value="removePrivilege" />
<input type="submit" name="submit" value="Remove organizer privilege" />
ENDSTRING;
        }
    else
        {
        echo <<< ENDSTRING
<input type="hidden" name="command" value="addPrivilege" />
<input type="submit" name="submit" value="Grant organizer privilege" />
ENDSTRING;
        }
    echo "</form>\n";
    echo "</div>\n";
    }

if (hasPrivilege('scheduler'))
    {
    echo "<table>\n";
    echo "<tr><th>Name</th><td>$row[name]</td></tr>\n";
    echo "<tr><th>E-mail</th><td>$row[email]</td></tr>\n";
    echo "<tr><th>Phone</th><td>$row[phone]</td></tr>\n";
    echo "<tr><th>Address</th><td>" . multiline($row['snailmail']) . "</td></tr>\n";
    echo "</table>\n";
    }
else echo "YOU'RE NOT A SCHEDULER";

$row = dbQueryByID('select user.name,card.role,card.email,card.phone,card.snailmail from card join user on card.userid=user.id where user.id=?',$user_id);
if ($row)
    {
    echo "<h2>Public contact info</h2>\n";
    echo "<table>\n";
    echo "<tr><th>Name</th><td>$row[name]</td></tr>\n";
    echo "<tr><th>Role</th><td>$row[role]</td></tr>\n";
    echo "<tr><th>E-mail</th><td>$row[email]</td></tr>\n";
    if ($row['phone'] != '')
        echo "<tr><th>Phone</th><td>$row[phone]</td></tr>\n";
    if ($row['snailmail'] != '')
        echo "<tr><th>Address</th><td>" . multiline($row['snailmail']) . "</td></tr>\n";
    echo "</table>\n";
    }

$stmt = dbPrepare('select id, title from proposal where proposerid=? and deleted=0 order by title');
$stmt->bind_param('i',$user_id);
$stmt->execute();
$stmt->bind_result($proposal_id, $title);
$first = true;
while ($stmt->fetch())
    {
    if ($first)
        {
        echo "<h2>Proposals</h2>\n";
        echo "<ul>\n";
        $first = false;
        }
    if ($title == '')
        $title = '!!NEEDS A TITLE!!';
    echo "<li><a href=\"proposal.php?id=$proposal_id\">$title</a></li>\n";
    }
if (!$first)
    echo "</ul>\n";
$stmt->close();

bifPagefooter();
?>
