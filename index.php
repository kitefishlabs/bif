<?php
require_once 'init.php';
require_once 'util.php';
require '../bif.php';
connectDB();

if (!loggedIn())
    {
    header('Location: loginForm.php');
    die();
    }

bifPageheader('proposals database');

if ((array_key_exists('adminmessage',$_SESSION)) && ($_SESSION['adminmessage'] != ''))
    {
    echo '<div style="background:#ffff80; padding:0.5em; width:790px;">' . $_SESSION['adminmessage'] . '</div>';
    unset($_SESSION['adminmessage']);
    }

echo "<ul>\n";
if (hasPrivilege('scheduler'))
    {
    echo <<<ENDSTRING
<li><a href="listBatches.php">Batches</a></li>
<li><a href="batch.php?id=0">All projects</a></li>
<li><a href="listDeleted.php">Deleted projects</a></li>
<li><a href="listVenues.php">Venues</a>
<ul><li>(<a href="listVenues.php?festival=960">2013 venues</a>)</li></ul>
</li>
<li><a href="calendar.php">Calendar</a></li>
<li><a href="listUsers.php">Users</a></li>
<br>
<li><a href="newProposal.php">New proposal</a></li>
<li><a href="newGroupshow.php">New group show</a></li>
<li><a href="newBatch.php">New batch</a></li>
<li><a href="newVenue.php">New venue</a></li>
<li><a href="newOrgField.php">New summary field</a></li>
<li><a href="newCard.php">New card (public contact)</a></li>
<li><a href="subscribe.php">Add person to mailing list</a></li>
<br>
<li><a href="preferences.php">Preferences</a></li>
ENDSTRING;
    }

$stmt = dbPrepare('select id,title from proposal where proposerid=? and deleted=0 order by title');
$stmt->bind_param('i',$_SESSION['userid']);
$stmt->execute();
$stmt->bind_result($proposalid,$title);
while ($stmt->fetch())
    echo "<li><a href='proposal.php?id=$proposalid'>$title</a></li>\n";
$stmt->close();
?>

<br>
<li><a href="editMyContact.php">Edit contact info</a></li>
<li><a href="changePassword.php">Change password</a></li>
<li><a href="logout.php">Logout</a></li>
</ul>

<?php
bifPagefooter();
?>
