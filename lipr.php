<?php
require __DIR__ . '/vendor/autoload.php';

function human_readable_bytes($number, $size='b') {
    if ($size == "kb") {
        $number = $number * 1024;
    }

    if ($number >= 1024 * 1024 * 1024) {
        return round($number / (1024 * 1024 * 1024), 1) . " GB";
    }
    elseif ($number >= 1024 * 1024) {
        return round($number / (1024 * 1024), 1) . " MB";
    }
    elseif ($number >= 1024) {
        return round($number / (1024), 1) . " KB";
    }
    else {
        return $number . " bytes";
    }
}
function percentage($value, $total = 0) {
    if ($total == 0) return 100;
    return ($value * 100 / $total);
}

use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;

//$ssh = new SSH2('');
$ssh = new SSH2('');
$key = new RSA();
$key->setPassword('');
//$key->loadKey(file_get_contents('D:\documenten\Jan Bouma\keys\40ND5Z1.ppk'));
$key->loadKey(file_get_contents(''));
//if (!$ssh->login('', $key)) {
if (!$ssh->login('', $key)) {
    exit('Login Failed');
}
/* OOM Killer */
$dmesgOomKiller = $ssh->exec('dmesg -T | grep -i "Killed process\|Out of memory\|oom-killer"');
$dmesgOomKillerParts = explode("\n", $dmesgOomKiller);
//print_r($dmesgOomKillerParts);

/* Swap areas */
class Swap {
    public $filename;
    public $type;
    public $size;
    public $used;
    public $priority;
}

$procSwaps = $ssh->exec('cat /proc/swaps | tail -n +2');
$procSwapsParts = explode("\n", $procSwaps);
array_pop($procSwapsParts);
$swaps = [];

foreach ($procSwapsParts as $procSwapsPart) {
    $parts = preg_split('/\s+/', $procSwapsPart);
    $swap = new Swap();
    $swap->filename = $parts[0];
    $swap->type = $parts[1];
    $swap->size = $parts[2];
    $swap->used = $parts[3];
    $swap->priority = $parts[4];
    $swaps[] = $swap;
}

/* Process Status */
$statusAll = $ssh->exec("sudo sh -c 'cat /proc/*/status'");
$statusArray = preg_split('/Name:/', $statusAll, 1, PREG_SPLIT_DELIM_CAPTURE);

class Proc {
    public $name;
    public $vmSwap;
}

$swapProcs = [];

foreach ($statusArray as $status) {
    $statusParts = explode("\n", $status);
    foreach ($statusParts as $part) {
        if (strpos($part, "Name:") !== false) {
            //echo $part.PHP_EOL;
            $name = trim(str_replace("Name:", "", $part));
            //echo $name.PHP_EOL;
        }
        if (strpos($part, "VmSwap:") !== false) {
            //echo $part.PHP_EOL;
            $vmSwap = trim(str_replace("VmSwap:", "", str_replace("kB", "", $part)));
            //echo $vmSwap.PHP_EOL;
            $proc = new Proc();
            $proc->name = $name;
            $proc->vmSwap = $vmSwap;
            $swapProcs[] = $proc;
        }

    }
}
$swapTotal = 0;

$swapProcsCum = [];

usort($swapProcs, function ($a, $b) {
    return strnatcmp($b->vmSwap, $a->vmSwap);
});

foreach ($swapProcs as $swapProc) {
    //echo $swapProc->name . ": " . $swapProc->vmSwap . PHP_EOL;
    if (!isset($swapProcsCum[$swapProc->name])) {
        $swapProcsCum[$swapProc->name] = 0;
    }
    $swapProcsCum[$swapProc->name] += $swapProc->vmSwap;
    $swapTotal += $swapProc->vmSwap;
}


arsort($swapProcsCum);
//echo "Total: " . $swapTotal . PHP_EOL;

$psMem = $ssh->exec("sudo sh -c 'ps -o pid:9,user:20,rss:10,%mem:6,command ax'");
?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css"
              integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO"
              crossorigin="anonymous">
        <title>Linux Profiler</title>
        <style>
            body {
                background-color: rgb(241, 244, 246);
            }

            .card-header {
                background-color: white;
                text-transform: uppercase;
                color: rgba(13, 27, 62, 0.5);
                font-weight: bold;
            }

            .table-sm th, .table-sm td {
                font-size: .9em;
                padding: 1px;
            }

            .container .row + .row {
                margin-top: 1em;
            }

            .footer {
                background-color: #18171b;
            }

            .footer h5, .footer li {
                font-size: .9em;
                color: darkgrey;
            }

            .footer .nav-item a {
                color: darkgrey;
            }
        </style>
    </head>
    <body>
    <div class='container'>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
            <a class="navbar-brand" href="#">
                <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-calculator text-warning"
                     fill="currentColor"
                     xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                          d="M12 1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H4z"/>
                    <path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2zm0 4a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm0 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-4z"/>
                </svg>
                Linux Profiler <small>0.1</small></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarMisc" role="button"
                           data-toggle="dropdown"
                           aria-haspopup="true" aria-expanded="false">
                            Misc
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarMisc">
                            <a class="dropdown-item" href="#database_engines">Database engines</a>
                            <a class="dropdown-item" href="#slow_queries">Slow queries</a>
                            <a class="dropdown-item" href="#binary_log">Binary log</a>
                            <a class="dropdown-item" href="#threads">Threads</a>
                            <a class="dropdown-item" href="#used_connections">Used connections</a>
                            <a class="dropdown-item" href="#innodb">InnoDB</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarMemory" role="button"
                           data-toggle="dropdown"
                           aria-haspopup="true" aria-expanded="false">
                            Memory
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarMemory">
                            <a class="dropdown-item" href="#memory_usage">Memory used</a>
                            <a class="dropdown-item" href="#key_buffer">Key buffer</a>
                            <a class="dropdown-item" href="#query_cache">Query cache</a>
                            <a class="dropdown-item" href="#sort_operations">Sort operations</a>
                            <a class="dropdown-item" href="#join_operations">Join operations</a>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarFile" role="button"
                           data-toggle="dropdown"
                           aria-haspopup="true" aria-expanded="false">
                            File
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarFile">
                            <a class="dropdown-item" href="#open_files">Open files</a>
                            <a class="dropdown-item" href="#table_cache">Table cache</a>
                            <a class="dropdown-item" href="#temp_tables">Temp. tables</a>
                            <a class="dropdown-item" href="#table_scans">Table scans</a>
                            <a class="dropdown-item" href="#table_locking">Table locking</a>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#status_variables">Status vars</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#system_variables">System vars</a>
                    </li>
                </ul>
            </div>
        </nav>
        <div class='row'>
            <a id='swaps'></a>
            <div class='col-sm-12'>
                <div class='card border-0 shadow-sm'>
                    <div class='card-header'>Swap areas</div>
                    <div class='card-body'>
                        <p><code>cat /proc/swaps</code></p>
                        <table class='table table-sm'>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Used</th>
                                <th>Priority</th>
                                <th>Usage</th>
                            </tr>
                            <?php
                            foreach ($swaps as $swap) {
                                $swapPct = percentage($swap->used, $swap->size);
                                ?>
                                <?= "<tr>" ?>
                                <?= "<td>" . $swap->filename . "</td>" ?>
                                <?= "<td>" . $swap->type . "</td>" ?>
                                <?= "<td>" . $swap->size . " <span class='text-muted'>(".human_readable_bytes($swap->size, 'kb').")</span></td>" ?>
                                <?= "<td>" . $swap->used . " <span class='text-muted'>(".human_readable_bytes($swap->used, 'kb').")</span></td>" ?>
                                <?= "<td>" . $swap->priority . "</td>" ?>
                                <td><div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $swapPct ?>%" aria-valuenow="<?= $swapPct ?>" aria-valuemin="0" aria-valuemax="100"><?= round($swapPct,1) ?></div>
                                    </div></td>
                                <?= "</tr>" ?>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class='row'>
            <a id='dmesg_oom'></a>
            <div class='col-sm-12'>
                <div class='card border-0 shadow-sm'>
                    <div class='card-header'>dmesg Out-of-memory killer</div>
                    <div class='card-body'>
                        <p><code>dmesg -T | grep -i "Killed process\|Out of memory\|oom-killer"</code></p>
                        <table class='table table-sm'>
                            <?php
                            foreach ($dmesgOomKillerParts as $line) {
                                echo "<tr><td>" . $line . "</td></tr>\n";
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class='row'>
            <div class='col-sm-12'>
                <div class='card border-0 shadow-sm'>
                    <div class='card-header'>Swap used by processes (cumulative)</div>
                    <div class='card-body'>
                        <div class='row'>
                            <div class='col-sm-6'>
                                <table class='table table-sm'>
                                    <tr>
                                        <th>Name</th>
                                        <th>Swap</th>
                                    </tr>
                                    <?php
                                    foreach ($swapProcsCum as $proc => $swap) {
                                        ?>
                                        <?= "<tr>" ?>
                                        <?= "<td>" . $proc . "</td>" ?>
                                        <?= "<td style='text-align: right'>" . $swap . "</td>" ?>
                                        <?= "</tr>" ?>
                                        <?php
                                    }
                                    ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class='row'>
            <div class='col-sm-12'>
                <div class='card border-0 shadow-sm'>
                    <div class='card-header'>Swap used by processes (seperated)</div>
                    <div class='card-body'>
                        <div class='row'>
                            <div class='col-sm-6'>
                                <table class='table table-sm'>
                                    <tr>
                                        <th>Name</th>
                                        <th>Swap</th>
                                    </tr>
                                    <?php
                                    foreach ($swapProcs as $proc) {
                                        ?>
                                        <?= "<tr>" ?>
                                        <?= "<td>" . $proc->name . "</td>" ?>
                                        <?= "<td style='text-align: right'>" . $proc->vmSwap . "</td>" ?>
                                        <?= "</tr>" ?>
                                        <?php
                                    }
                                    ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <pre>
        <?= $psMem ?>
    </pre>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
            integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
            crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"
            integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49"
            crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"
            integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy"
            crossorigin="anonymous"></script>
    </body>
    </html>

<?php

