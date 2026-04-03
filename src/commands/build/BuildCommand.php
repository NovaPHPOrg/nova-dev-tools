<?php

namespace nova\commands\build;

use nova\commands\BaseCommand;
use nova\console\Output;

class BuildCommand extends BaseCommand
{
    //打包进数据目录

    private $nova;
    private $output;

    private $zip;
    public function __construct($workingDir, $options)
    {
        parent::__construct($workingDir, $options);
        $this->nova = json_decode(file_get_contents($workingDir . DIRECTORY_SEPARATOR . "package.json"),true);
        $this->zip = $this->workingDir. DIRECTORY_SEPARATOR . "dist";
        $this->output = $this->zip .DIRECTORY_SEPARATOR."temp";
    }

    public function init()
    {
        Output::section("Build Project");
        $this->removePath($this->zip);
        mkdir($this->output, 0777, true);
        $this->copyDir($this->workingDir . DIRECTORY_SEPARATOR . "src", $this->output);

        $version = $this->prompt("Version", $this->nova['version']);
        Output::info("Building version: $version");

        $config            = include $this->output . DIRECTORY_SEPARATOR . "config.php";
        $config["version"] = $version;
        $config["debug"]   = false;
        $config            = "<?php\nreturn " . var_export($config, true) . ";";
        file_put_contents($this->output . DIRECTORY_SEPARATOR . "example.config.php", $config);
        unlink($this->output . DIRECTORY_SEPARATOR . "config.php");

        $this->removePath($this->output . DIRECTORY_SEPARATOR . "runtime");
        mkdir($this->output . DIRECTORY_SEPARATOR . "runtime", 0777, true);

        Output::step("Packing archive…");
        $zip = new \ZipArchive();
        $zip->open($this->zip . DIRECTORY_SEPARATOR . $this->nova['name'] . "-" . $version . ".zip", \ZipArchive::CREATE);
        $this->addFileToZip($this->zip, $zip);
        $zip->close();
        $this->removePath($this->output);

        Output::writeln();
        Output::success("Project packed → dist/{$this->nova['name']}-{$version}.zip");
        Output::writeln();
    }


    private function addFileToZip(string $string, \ZipArchive $zip)
    {
        $dir = opendir($string);
        while ($file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($string . DIRECTORY_SEPARATOR . $file)) {
                    $this->addFileToZip($string . DIRECTORY_SEPARATOR . $file, $zip);
                } else {
                    $zip->addFile($string . DIRECTORY_SEPARATOR . $file, str_replace($this->output . DIRECTORY_SEPARATOR, "", $string . DIRECTORY_SEPARATOR . $file));
                }
            }
        }
        closedir($dir);
    }
}