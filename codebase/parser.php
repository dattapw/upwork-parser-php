<?php
include('vendor/autoload.php');

global $_UPWORKFEEDURL; $_UPWORKFEEDURL = "https://www.upwork.com/ab/feed/jobs/rss?sort=recency&paging=[STARTINDEX];B[ITEMCOUNT]&api_params=1&q=&securityToken=[SECURITYTOKEN]&userUid=[USERUID]&orgUid=[ORGUID]";
global $_STARTINDEX; $_STARTINDEX = 0;
global $_ITEMCOUNT; $_ITEMCOUNT = 50;
global $_SECURITYTOKEN;
global $_USERUID;
global $_ORGUID;
global $_TOTALJOBS;
global $_OUTPUTFILE; $_OUTPUTFILE = 'UpWork_data_' . time() . '.csv';

function DownloadContent
(
    $url = ''
){
    $data = null;
    try{
        $client = new \Guzzle\Http\Client();
        $request = $client->createRequest('GET',$url);
        $response = $request->send();
        $data = new SimpleXMLElement($response->getBody(),LIBXML_NOCDATA);
    }catch (\Exception $e)
    {
        \cli\err('[ERROR]: '. $e->getMessage());
        \cli\line('[LINE NUMBER]: '. $e->getLine());
        \cli\line('[STACK TRACE]: '.$e->getTraceAsString());
    }
    return $data;
}

function createRequest
(
    $paging = 0
)
{
    $xml_data = null;
    try
    {
        global $_UPWORKFEEDURL;
        global $_ITEMCOUNT;
        global $_SECURITYTOKEN;
        global $_USERUID;
        global $_ORGUID;

        $target_url = $_UPWORKFEEDURL;
        $target_url = str_replace('[STARTINDEX]', $paging, $target_url);
        $target_url = str_replace('[ITEMCOUNT]', $_ITEMCOUNT, $target_url);
        $target_url = str_replace('[SECURITYTOKEN]', $_SECURITYTOKEN, $target_url);
        $target_url = str_replace('[USERUID]', $_USERUID, $target_url);
        $target_url = str_replace('[ORGUID]', $_ORGUID, $target_url);

        \cli\line('Processing Request: '. $target_url);
        $xml_data = DownloadContent($target_url);
    }
    catch(\Exception $e)
    {
        \cli\err('[ERROR]: '. $e->getMessage());
        \cli\line('[LINE NUMBER]: '. $e->getLine());
        \cli\line('[STACK TRACE]: '.$e->getTraceAsString());
    }
    return $xml_data;
}

function parseXML
(
    $xml_data = []  /** @var SimpleXMLElement $xml_data */
)
{
    $parsed_data = [];
    try
    {
        $iterator_node = $xml_data->channel->item;
        foreach($iterator_node as $job_node)
        {
            $parsed_data[] =
                [
                    (string)$job_node->title,
                    (string)$job_node->link,
                    (string)$job_node->description,
                    (string)$job_node->pubDate,
                    (string)$job_node->guid
                ];
        }
    }
    catch(\Exception $e)
    {
        \cli\err('[ERROR]: '. $e->getMessage());
        \cli\line('[LINE NUMBER]: '. $e->getLine());
        \cli\line('[STACK TRACE]: '.$e->getTraceAsString());
    }

    \cli\line("[Jobs] ".count($parsed_data)." found.");
    return $parsed_data;
}

function writeToFile
(
    $parsed_data = []
)
{
    try{

        global $_OUTPUTFILE;
        $file_handle = \League\Csv\Writer::createFromPath($_OUTPUTFILE,'w+');
        $file_handle->insertAll($parsed_data);

    }
    catch(\Exception $e)
    {
        \cli\err('[ERROR]: '. $e->getMessage());
        \cli\line('[LINE NUMBER]: '. $e->getLine());
        \cli\line('[STACK TRACE]: '.$e->getTraceAsString());
    }
}


\cli\line("Upwork Parser v1.0.0 (23-06-2019)");
$_STARTINDEX = (int)(\cli\prompt("Starting job index",0,":",false));
$_SECURITYTOKEN = \cli\prompt("Security Token","5d377b160d07264038540817ed913eea22adc31dd80981246c2b308b32b3760f807d99e1b319630de8e01769bdd0c7b52c4a7cbe13141ed7c287b2d5318d2b91",":",false);
$_USERUID = \cli\prompt("User UID","1138018259854802944",":",false);
$_ORGUID = \cli\prompt("Organization UID","1138018259854802946",":",false);
$_TOTALJOBS = \cli\prompt("Number of jobs to parse (Multiples of 50)",(int)50,":",false);
$_OUTPUTFILE = \cli\prompt("Output file name",$_OUTPUTFILE,":",false);

writeToFile([['Job Title', 'Link', 'Description', 'Publication Date', 'Unique ID']]);

for($paging = (int)$_STARTINDEX; $paging <= (int)$_TOTALJOBS; $paging += 50)
{
    $xml_data = createRequest($paging);
    $parsed_data = parseXML($xml_data);
    writeToFile($parsed_data);
}

