<?php
    $version="v1.1";

    // username/password authentication
	if (file_exists('.editor-auth.php')) require('.editor-auth.php');
	if (empty($edituser) || empty($editpass))
	{
        // user/pass hasn't been set
		if (empty($_POST['edituser']) || empty($_POST['editpass']))
		{
            // prompt for it
			exit("<html><h3>Enter authentication for editor:</h3>
			<form method=\"post\" action=\"editor.php\">
			Username: <input type=\"text\" name=\"edituser\" /><br />
			Password: <input type=\"password\" name=\"editpass\" /><br />
			<input type=\"submit\" name=\"submit\" value=\"Submit\" />
			</form></html>");
		}
		else
		{
            // store it privately
			$edituser=$_POST['edituser'];
			$editpass=$_POST['editpass'];
			file_put_contents('.editor-auth.php','<'."?php
			global \$edituser,\$editpass;
			\$edituser='$edituser';
			\$editpass='$editpass';
			");
			chmod('.editor-auth.php',0700);
		}
	}

    // if authoriztion not provided, force it
    if (empty($_SERVER['PHP_AUTH_USER']) || 
		empty($_SERVER['PHP_AUTH_PW']) ||
		$_SERVER['PHP_AUTH_USER']!=$edituser ||
		$_SERVER['PHP_AUTH_PW']!=$editpass)
	{
		header('HTTP/1.1 401 Unauthorized');
		header("WWW-Authenticate: Basic realm=\"Editor\"");
        exit('Unauthorized');
	}

    // save file being edited
	if ($_SERVER['REQUEST_METHOD']=="POST")
    {
        if (empty($_POST['editfile'])) die('no filename');
        if (file_put_contents($_POST['editfile'],$_POST['content'])===false)
            die('Error writing file');
        chmod($_POST['editfile'],0755); // assume apache may need readability
        exit("-SUCCESS-");
    }

    // run command (shell prompt)
    if (!empty($_GET['cmd']))
    {
        $home=dirname($_SERVER['SCRIPT_FILENAME']);

        foreach ($_SERVER as $var => $value)
            putenv("$var=$value");

        $cmd=$_GET['cmd'];
        $fp=popen("/usr/sbin/shellinaboxd --cgi -t -s \"/:\$(/usr/bin/id -u):\$(/usr/bin/id -g):$home:$cmd\" 2>&1","r");
        $valid=array('X-ShellInABox-Port','X-ShellInABox-Pid','Content-type');
        while ($line=trim(fgets($fp)))
        {
            if ($line=="") break;
            $exp=explode(':',$line,2);
            if (!in_array($exp[0],$valid))
                die("ERROR: ".$line);
            header($line);
        }
        while ($line=fgets($fp))
        {
            echo $line;
            if (trim($line)=="</html>") break;
        }
        exit(false);
    }

    // get a list of files
    $dirs=new RecursiveDirectoryIterator(".");
    $files=new RecursiveIteratorIterator($dirs);

    $newfile="&#171; New File &#187;";

    $editfile='';
    if (!empty($_GET['file']))
        $editfile=$_GET['file'];

    // build form in toolbar
    $form="
<form class=\"form-inline\">
    <div class=\"input-append\">
        <button type=\"submit\" id=\"save\" class=\"btn\">Save</button>
        <span class=\"divider-vertical\"></span>
        <button type=\"submit\" id=\"run\" class=\"btn\">Run</button>
        <span class=\"divider-vertical\"></span>
        <select class=\"input\" id=\"file\" name=\"file\">
        <option>$newfile</option>
";
    foreach ($files as $file)
    {
        $path=substr($file,2);
        if ($path[0]==".") continue;
        if (strstr($path,'/.')) continue;
        if (is_dir($path)) continue;
        if (!is_file($path)) continue;

        $sel='';
        if ($path==$editfile) $sel=" selected";

        $form.="<option value=\"$path\"$sel>$path</option>\n";
    }
    $form.="</select>
        <button type=\"submit\" class=\"btn\">Edit</button>
        <span class=\"divider-vertical\"></span>
        <a target=\"_blank\" href=\"editor.php?cmd=sh\" class=\"btn\">Shell</a>
    </div>
</form>\n";

// HTML5 using Bootstrap & Codemirror 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Editor</title>
    <meta charset="utf-8" />
    <link href="//netdna.bootstrapcdn.com/bootswatch/2.1.1/cerulean/bootstrap.min.css" rel="stylesheet">
    <link href="//cdn.jsdelivr.net/codemirror/2.37/codemirror.css" rel="stylesheet">
    <style type="text/css">
        .CodeMirror {
            border: 1px solid #eee;
            height: auto;
        }
        .CodeMirror-scroll {
            overflow-y: hidden;
            overflow-x: auto;
        }
    </style>
</head>
<body id="page1">
    <div id="navbar1" class="navbar">
        <div id="div1" class="navbar-inner">
            <a id="link1" class="brand" href="#">Editor <?php echo $version; ?></a>
            <span id="navlist1" class="pull-right">
                <?php echo $form; ?>
            </span>
        </div>
    </div>
    <div id="workspace" style="height: auto; border: 1px solid;">
<?php
    // push the current file contents to codemirror via textarea
    $editfile='';
	$content="";
    if (!empty($_GET['file']))
    {
        $editfile=$_GET['file'];
		if (file_exists($editfile))
			$content=file_get_contents($editfile);
		else
			$editfile='';
	}
	echo "<input type=\"hidden\" id=\"editfile\" name=\"editfile\" value=\"$editfile\" />";
	echo '<textarea id="editbox">'.htmlentities($content).'</textarea>';

    // tail portion of HTML with JS libraries
?>
    </div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/js/bootstrap.min.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/codemirror.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/mode/xml/xml.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/mode/javascript/javascript.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/mode/css/css.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/mode/clike/clike.js"></script>
<script src="//cdn.jsdelivr.net/codemirror/2.37/mode/php/php.js"></script>

<script type="text/javascript">

    // fix the iframe height
    function setIframeHeight()
    {
        var iframe=document.getElementById('runbox');
        var iframeWin = iframe.contentWindow || iframe.contentDocument.parentWindow;
        iframe.height = iframeWin.document.documentElement.scrollHeight || iframeWin.document.body.scrollHeight;
    };

    // after document load enable CodeMirror
$(document).ready(function(){
    var editor = CodeMirror.fromTextArea(document.getElementById("editbox"), {
        lineNumbers: true,
        lineWrapping: true,
        matchBrackets: true,
        mode: "application/x-httpd-php",
        indentUnit: 4,
        indentWithTabs: true,
        enterMode: "keep",
        tabMode: "shift",
    });
    // how to save edited file from CodeMirror (sends to POST above)
	function save(run)
	{
        var editfile=$('#editfile').val();
		if (editfile=='') editfile=prompt("Enter filename");
		editor.save();
        var content=editor.getValue();
        $.ajax({
            type: "POST",
            url: "editor.php",
            data: {editfile:editfile,content:content},
            success: function(data){
                if (data!='-SUCCESS-')
                    alert('SAVE FAILED: '+data);
                else
				if (run)
				{
					//window.open(editfile,'_blank');
					//window.focus();
                    $('#workspace').empty().append('<iframe id="runbox" onLoad="setIframeHeight()" width="100%" allowtransparency=true frameborder=0 scrolling=no src="'+editfile+'"></iframe>');
				}
				else
					window.location.href='editor.php?file='+editfile;
            },
            error: function(xhr){
                alert('ERROR: '+xhr.status+' '+xhr.statusText+' '+xhr.responseText);
            }
        });
	}
    // save and run buttons
    $('#save').click(function(){
		save(false);
        return false;
    });
	$('#run').click(function(){
		save(true);
		return false;
	});
	
});
</script>
</body>
</html>
