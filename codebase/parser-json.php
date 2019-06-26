<?php
include('vendor/autoload.php');

global $_APIURL; $_APIURL = 'https://www.upwork.com/o/jobs/browse/url?page=[PAGE]&per_page=50&sort=recency';
global $CONFIG_COOKIE;
global $CONFIG_PAGES; $CONFIG_PAGES = 1;
global $OUTPUTFILE; $OUTPUTFILE = 'UpWork-'.time().'.csv';
global $WRITER; $WRITER = \League\Csv\Writer::createFromPath($OUTPUTFILE, 'w+');

/** Rate limiting */
global $RL_REQUESTS; $RL_REQUESTS = 0;
global $RL_TIMEINIT; $RL_TIMEINIT = time();
global $RL_LIMIT; $RL_LIMIT = 50/120;
global $RL_SLEEP; $RL_SLEEP = 2;
global $RL_PENALITY; $RL_PENALITY = 30;

function rlUpdate()
{
    global $RL_REQUESTS;
    $RL_REQUESTS++;
}

function rlIsSafe()
{
    global $RL_REQUESTS;
    global $RL_TIMEINIT;
    global $RL_LIMIT;

    $current_rate = $RL_REQUESTS / ( (time() - $RL_TIMEINIT) );
    \cli\line("[RATELIMIT]: ".round($current_rate,3)." reqs/second compared to limit ".round($RL_LIMIT,3)." reqs/second");

    return $current_rate < $RL_LIMIT ? true : false;
}

function dlContent
(
    $url,
    $cookie
)
{
    global $RL_PENALITY;
    global $RL_SLEEP;

    while(!rlIsSafe())
    {
        sleep($RL_PENALITY);
    }

    rlUpdate();
    $content = null;
    try
    {
        \cli\line('[GET] '.$url);
        $client = new \Guzzle\Http\Client($url,
            [
                'request.options' =>
                    [
                        'headers' =>
                        [
                            'cache-control' => 'no-cache',
                            'Connection' => 'keep-alive',
                            'accept-encoding' => 'gzip, deflate',
                            'Host' => 'www.upwork.com',
                            'Cache-Control' => 'no-cache',
                            'Accept' => '*/*',
                            'x-requested-with' => 'XMLHttpRequest',
                            'cookie' => $cookie
                        ]

                    ]
            ]
        );

        $client->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.80 Safari/537.36');

        $request = $client->createRequest(
            'GET',
            $url,
            [
            ],
            null,
            [
                'debug' => false,
                'allow_redirects' => true,
                'verify' => false
            ]
        );

        //Sleep to preserve API Rate Limits
        sleep($RL_SLEEP);

        // Send the HTTP request
        $response = $request->send();
        $content = $response->getBody();
    }
    catch (\Exception $e)
    {
        \cli\line('[ERROR] '.$e->getMessage());
    }
    return $content;
}


function createRequest
(
    $page,
    $url,
    $cookie
)
{
    $target_url = str_replace('[PAGE]',$page,$url);
    $content = dlContent($target_url, $cookie);
    return $content;
}


function parseContent
(
    $json_content
)
{
    $array_content = [];
    try{
        $array = json_decode($json_content, true);
        $array_content = $array['searchResults']['jobs'];
    }
    catch (\Exception $e)
    {
        \cli\line('[ERROR] '.$e->getMessage());
    }
    return $array_content;
}


function saveContent
(
    $array_content,
    $filename,
    $headers
)
{
    /** @var \League\Csv\Writer $WRITER */
    global $WRITER;

    $jobs = [];

    if(!$headers)
    {

        foreach ($array_content as $job)
        {
            $skills = [];
            foreach($job['skills'] as $skill)
            {
                $skills[] = $skill['prettyName'];
            }

            $jobs[] =
                [
                    $job['title'],
                    join(',', $skills),
                    $job['description'],
                    $job['proposalsTier'],
                    $job['createdOn'],
                    $job['type'],
                    $job['ciphertext'],
                    $job['category2'],
                    $job['subcategory2'],
                    $job['duration'],
                    $job['amount']['amount'],
                    $job['recno'],
                    $job['uid'],
                    $job['client']['paymentVerificationStatus'],
                    $job['client']['location']['country'],
                    $job['client']['totalSpent'],
                    $job['client']['totalReviews'],
                    $job['client']['totalFeedback'],
                    $job['client']['feedbackText'],
                    $job['publishedOn'],
                    "https://www.upwork.com/jobs/".$job['ciphertext']."/"
                ];
        }
        \cli\line('[CSV] Found '.count($jobs).' jobs.');

    }else{

        $jobs[] = [
            'Title',
            'Skills',
            'Description',
            'Proposals',
            'Created On',
            'Type',
            'Cipher Text',
            'Category',
            'Sub-category',
            'Duration',
            'Amount',
            'Rec No.',
            'UID',
            'Client Payment Verified',
            'Client Country',
            'Client Total Spent',
            'Client Total Reviews',
            'Client Feedback',
            'Client Feedback Text',
            'Published On',
            'URL'
        ];
        \cli\line('[CSV] Inserted Headers.');

    }

    $WRITER->setNewline("\r\n");
    $WRITER->insertAll($jobs);
}


\cli\line('Upwork Parser v1.0.0. (Created by Saurabh Datta)');


$CONFIG_COOKIE = (string)(\cli\prompt('Cookies',null,':',false));
if(empty($CONFIG_COOKIE))
{
    $CONFIG_COOKIE = (string)file_get_contents('default.cookie');
}

$CONFIG_PAGES = (int)(\cli\prompt('Pages',10,':',false));

saveContent([],$OUTPUTFILE, true);

for($page = 1; $page <= $CONFIG_PAGES; $page++)
{
    $json_content = createRequest($page, $_APIURL, $CONFIG_COOKIE);
    $array_content = parseContent($json_content);
    saveContent($array_content, $OUTPUTFILE, false);
}