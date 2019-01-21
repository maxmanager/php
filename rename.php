<style>
table {
  border-collapse: collapse;
}

table, th, td {
  border: 1px solid black;
  padding: 4px;
}
</style>
<?php
setlocale (LC_ALL, 'pt_BR');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "pdf.php";

function itau($filename)
{
    $dom = new DOMDocument();
    @$dom->loadHTMLFile($filename);

    $tablePosition = str_replace("-", "", $filename) < 201812 ? 1 : 0;

    $xpath = new DOMXpath($dom);
    $element = $xpath->query("//table")->item($tablePosition);
    
    if (!is_null($element))
    {
        if ($tablePosition > 0)
        {
            foreach ($xpath->query("./tr[position() > 1]", $element) as $tr)
            {
                if ($tr->childNodes->item(10)->nodeValue == "") continue;
        
                echo "<tr>";
                echo "<td>" . utf8_decode($tr->childNodes->item(6)->nodeValue) . "</td>";
                echo "<td style='text-align: right'>" . number_format($tr->childNodes->item(10)->nodeValue, 2, ",", ".") . "</td>";
                echo "<td>" . utf8_decode($tr->childNodes->item(2)->nodeValue) . "/" . substr($filename, 0, 4) . "</td>";
                echo "</tr>";
            }
        }
        else
        {
            foreach ($xpath->query("./tr[position() > 11]", $element) as $tr)
            {
                if ($tr->childNodes->item(5)->nodeValue == "") continue;

                echo "<tr>";
                echo "<td>" . utf8_decode($tr->childNodes->item(4)->nodeValue) . "</td>";
                echo "<td style='text-align: right'>" . number_format($tr->childNodes->item(5)->nodeValue, 2, ",", ".") . "</td>";
                echo "<td>" . preg_replace('/[^A-Za-z0-9\/\-]/', '', $tr->childNodes->item(1)->nodeValue) . "/" . substr($filename, 0, 4) . "</td>";
                echo "</tr>";
            }
        }        
    }
    else
    {
        echo "FAIL";
    }
}

function nubank($entry)
{
    if (false !== ($handle = fopen("nubank/" . $entry, "r")))
    {
        fgetcsv($handle);

        while (false !== ($data = fgetcsv($handle, 1000, ",")))
        {
            echo "<tr>";
            echo "<td>" . strtoupper(utf8_decode($data[2])) . "</td>";
            echo "<td style='text-align: right'>" . number_format($data[3], 2, ",", ".") . "</td>";
            echo "<td>" . date("d/m/Y", strtotime($data[0])) . "</td>";
            echo "<td>" . utf8_decode($data[1]) . "</td>";
            echo "</tr>";
        }

        fclose($handle);
    }
}

function cartaoitau($entry)
{
    $a = new PDF2Text();
    $a->setFilename($entry); 
    $a->decodePDF();

    $x = 0;

    $valores = false;

    $categoria = false;

    $ano = 0;
    
    foreach (explode("\n", $a->output()) as $i => $linha)
    {
        if ($linha == "VALOR EM R$")
        {
            $valores = true;
        }

        if (preg_match('/^Vencimento:/', $linha))
        {
            preg_match('/\d{2}\/\d{2}\/(\d{4})/', $linha, $re);
            $ano = $re[1];
        }

        if ($valores)
        {
            if (preg_match('/^[\d]{2}\/[\d]{2}$/', $linha))
            {
                echo "<tr>";
                echo "<td>" . $linha . "/" . $ano . "</td>";
                $x = $i;

                continue;
            }
            
            if ($x != 0)
            {
                if ($i - $x == 1)
                {
                    echo "<td>" . $linha . "</td>";
                }
                else if ($i - $x == 2)
                {
                    $valor = str_replace(",", ".", str_replace(".", "", $linha));
                    
                    if (is_numeric($valor))
                    {
                        echo "<td>" . number_format($valor, 2, ",", ".") . "</td>";
                    }
                    
                    $x = 0;
                    $categoria = true;
                }
                
                if ($linha == "Continua...")
                {
                    $valores = false;
                    echo "</tr>";
                }
            }
            else if ($categoria && preg_match('/^[^a-z0-9]+$/', $linha))
            {
                preg_match('/^([A-ZÀ-Ú]+)/', $linha, $descricao);
                echo "<td>" . $descricao[1] . "</td>";
                $categoria = false;
            }
        }

        //echo $linha . "<br>";
    }

    $valores = false;
}

echo "<table>";
if ($dir = opendir('./'))
{    
    while (false !== ($entry = readdir($dir)))
    {
        if ($entry != "." && $entry != ".." && substr($entry, -3) != "php" && strpos($entry, ".") !== false)
        {
            //nubank($entry);
            //itau($entry);

            cartaoitau($entry);
        }
    }

    closedir($dir);
}
echo "</table>";