<?php
namespace Scraper;

use Symfony\Component\DomCrawler\Crawler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Goutte\Client;
use Stash;
use GuzzleHttp;

class Scraper
{
    protected $proxy = [];
    protected $stash = null;

    public function __construct(string $logPath = null, string $stashPath = null)
    {
        $this->logPath = $logPath;
        $this->stashPath = $stashPath;
        $this->scraperInit();
    }

    public function scraperInit()
    {
        if (!is_null($this->logPath)) {
            $this->monolog = new Logger('debug');
            $this->monolog->pushHandler(new StreamHandler($this->logPath.'/debug.log', Logger::INFO));
        }

        $this->loadProxies();

        if (!is_null($this->stashPath)) {
            $this->useStash();
        }
    }

    public function useStash()
    {
        if (is_null($this->stashPath)) {
            return;
        }

        $driver = new Stash\Driver\FileSystem(['path' => $this->stashPath.'/stash/']);
        $this->stash = new Stash\Pool($driver);
        $this->stash->purge();
    }

    public function loadProxies()
    {
        $proxiFile = __dir__.'/../proxy.txt';
        if (!file_exists($proxiFile)) {
            return false;
        }
        $this->log('info', 'load proxies');

        $proxiesInfo = file_get_contents($proxiFile);
        $proxies = explode("\n", $proxiesInfo);
        foreach ($proxies as $proxy) {
            if (empty($proxy)) {
                continue;
            }
            list($ip, $port, $user, $pass) = explode(':', $proxy);

            $this->proxy[] = [
                'settings' => 
                [
                    'ip' => $ip,
                    'port' => $port,
                    'user' => $user,
                    'pass' => $pass,
                    'agent' => $this->getUseragentRandom()
                ]
            ];
        }
    }

    public function getUseragentRandom()
    {
        $agentFile = __dir__.'/../useragent.txt';
        if (!file_exists($agentFile)) {
            return false;
        }
        $this->log('info', 'load proxies');

        $agentsData = file_get_contents($agentFile);
        $agents = explode("\n", $agentsData);
        $id = rand(0, count($agents)-1);

        if (isset($agents[$id])) {
            return $agents[$id];
        }

        return false;
    }

    protected function getProxy()
    {
        $hasProxy = false;

        if (count($this->proxy) == 0) {
            return false;
        }

        $this->log('info', 'find proxy');

        while (!$hasProxy) {
            foreach ($this->proxy as $key => $info) {
                if (!isset($info['next']) || $info['next'] < time()) {
                    $this->proxy[$key]['next'] = time() + rand(10, 30);
                    $this->log('info', 'use proxy', $info);
                    return $this->proxy[$key]['settings'];
                }
            }
            $this->log('info', 'free proxy not found');
            sleep(1);
        }

        return false;

    }

    public function request($key, $url)
    {
        $this->log('info', 'new request', ['key' => $key, 'url' => $url]);

        if ($this->stash) {
            $item = $this->stash->getItem($key.'/'.md5($url));
            $data = $item->get();

            if(!$item->isMiss()) {
                return (string) $data;
            }
        }

        $clientParameters = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.1 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36',
            ],
        ];

        if ($proxy = $this->getProxy())
        {
            $clientParameters = [
                'headers' => [
                    'User-Agent' => $proxy['agent'],
                ],
            ];

            $clientParameters['curl'] = [
                CURLOPT_PROXY => $proxy['ip'],
                CURLOPT_PROXYPORT => $proxy['port'],
                CURLOPT_PROXYUSERPWD => $proxy['user'].':'.$proxy['pass'],
            ];
        }

        try {
            $client = new GuzzleHttp\Client(['http_errors' => false]);
            $response = $client->request('GET', $url, $clientParameters);
            $data = $response->getBody();

            if ($this->stash) {
                $item = $this->stash->getItem($key.'/'.md5($url));
                $item->lock();
                $data = (string) $data;
                $this->stash->save($item->set($data));
            }
        } catch (\Exception $e) {
            $this->log('error', 'guzzle request', (array) $e);
            return false;
        }

        return (string) $data;
    }

    public function memoryLog($msg = null)
    {
        if (!is_null($msg)) {
            $this->output->writeln($msg);
        }

        $info = sprintf('%dKB/%dKB', round(memory_get_usage(true) / 1024), memory_get_peak_usage(true) / 1024);

        $this->log('info', $info);
    }

    public function saveFile($url, $file)
    {
        $source = $this->request('image', $url);
        return file_put_contents($file, $source);
    }

    protected function log($level, $message, $xtra = [])
    {
        if (is_null($this->logPath)) {
            return;
        }
        $this->monolog->{$level}($message, $xtra);
    }
}
