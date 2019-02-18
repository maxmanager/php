<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1">
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
mb_internal_encoding('UTF-8');

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
                echo "<td style='text-align: right'>" . number_format(str_replace(array(",", " "), "", $tr->childNodes->item(10)->nodeValue), 2, ",", ".") . "</td>";
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
                echo "<td style='text-align: right'>" . number_format(str_replace(array(",", " "), "", $tr->childNodes->item(5)->nodeValue), 2, ",", ".") . "</td>";
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

class Dados
{
    public $data;
    public $descricao;
    public $valor;
    public $categoria;
    public $par;

    public function getCategoria()
    {
        return $this->categoria ?: "Taxa";
    }

    public function getData()
    {
        return date("d/m/Y", $this->data);
    }

    public function getValor()
    {
        return number_format($this->valor, 2, ",", ".");
    }
}


function cartaoitau($entry, &$dados)
{
    $a = new PDF2Text();
    $a->setFilename($entry); 
    $a->decodePDF();

    $x = 0;
    $valores = false;
    $vencimento = null;
    $dado = null;
    $j = 0;
    $soma = 0;
    $captura_encargos = false;
    $encargos = null;
    $captura_iof = false;
    $iofs = array();
    $temp = array();
    
    foreach (explode("\n", $a->output()) as $i => $linha)
    {
        if (preg_match('/^Vencimento:/', $linha))
        {
            preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $linha, $re);
            $vencimento = mktime(0, 0, 0, $re[2], $re[1], $re[3]);
        }
        
        if ($captura_encargos)
        {
            $valor = str_replace(",", ".", str_replace(array(".", " "), "", $linha));
                    
            if (is_numeric($valor))
            {
                $encargos = $valor;
            }

            $captura_encargos = false;
        }

        if ($captura_iof)
        {
            $valor = str_replace(",", ".", str_replace(array(".", " "), "", $linha));
                    
            if (is_numeric($valor))
            {
                $iofs[] = $valor;
            }

            $captura_iof = false;
        }

        switch ($linha)
        {
            case "Lançamentos: compras e saques":
                $valores = true;
                break;

            case "Continua...":
                $valores = false;
                break;

            case "Encargos (financiamento + moratório)":
                $captura_encargos = true;
                break;

            case "Repasse de IOF em R$":
                $captura_iof = true;
                break;
        }
        
        if ($valores)
        {
            if (preg_match('/^[\d]{2}\/[\d]{2}$/', $linha))
            {
                $x = $i;

                $ano = date("Y", $vencimento);
                
                if (date("m", $vencimento) < (int)substr($linha, 3, 2))
                {
                    $ano = date("Y", strtotime("-1 year", $vencimento));
                }

                $dia_mes = explode("/", $linha);

                $dado = new Dados();
                $dado->data = mktime(0, 0, 0, $dia_mes[1], $dia_mes[0], $ano);

                $j = array_push($temp, $dado);
                
                $dado->par = $j % 2 == 0;

                continue;
            }
            
            if ($x != 0)
            {
                if ($i - $x == 1)
                {
                    if (preg_match('/([\d]{2})\/[\d]{2}$/', $linha, $descricao))
                    {
                        $dado->data = strtotime("+" . $descricao[1] - 1 . " months", $dado->data);
                    }

                    if (strpos($linha, "DIFERENCA COTACAO U$"))
                    {
                        $dado->data = strtotime("-1 day", $dado->data);
                    }

                    $dado->descricao = $linha;
                }
                else if ($i - $x == 2)
                {
                    $valor = str_replace(",", ".", str_replace(array(".", " "), "", $linha));
                    
                    if (is_numeric($valor))
                    {
                        $dado->valor = $valor;
                    }
                    
                    $x = 0;
                }
                
                if ($linha == "Continua...")
                {
                    $valores = false;
                }
            }
            else if (preg_match('/^([A-ZÀ-Ú ]+)\./', $linha, $categoria))
            {
                if ($dado->categoria != "")
                {
                    $temp[$j-2]->categoria = $dado->categoria;
                }

                $dado->categoria = ucfirst(mb_strtolower(utf8_encode($categoria[1])));
            }
        }
    }

    $f = explode("-", date("m-d-Y", strtotime('-1 month, last day of this month', $vencimento)));
    $data_fechamento = mktime(0, 0, 0, $f[0], $w = $f[1] < 30 ? $f[1] : 30, $f[2]);

    if ($encargos != null)
    {
        $dado = new Dados();
        $dado->descricao = "ENCARGOS";
        $dado->valor = $encargos;
        $dado->data = strtotime("-1 day", $data_fechamento);
        $dado->categoria = "Taxa";

        array_push($temp, $dado);
    }
    
    foreach ($iofs as $iof)
    {
        if ($iof > 0)
        {
            $dado = new Dados();
            $dado->descricao = "IOF";
            $dado->valor = $iof;
            $dado->data = strtotime("-1 day", $data_fechamento);
            $dado->categoria = "Taxa";
    
            array_push($temp, $dado);
        }
    }

    $temp = array_filter($temp, function ($registro) use ($data_fechamento) {
        return $registro->data < $data_fechamento && $registro->valor != 0;
    });

    $dados = array_merge($dados, $temp);
}


$dados = array();

echo "<table>";
if ($dir = opendir('./'))
{    
    while (false !== ($entry = readdir($dir)))
    {
        if ($entry != "." && $entry != ".." && substr($entry, -3) != "php" && strpos($entry, ".") !== false)
        {
            //nubank($entry);
            itau($entry);
            //cartaoitau($entry, $dados);
        }
    }

    closedir($dir);
}

usort($dados, function($first, $second){
    return $first->data > $second->data;
});



foreach ($dados as $dado)
{
    echo "<tr><td>{$dado->descricao}</td><td>{$dado->getValor()}</td><td>{$dado->getData()}</td><td>{$dado->getCategoria()}</td></tr>\n";
}
echo "</table>";