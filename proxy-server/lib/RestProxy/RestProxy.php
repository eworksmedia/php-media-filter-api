<?php
namespace RestProxy;

class RestProxy
{
    private $request;
	private $files;
    private $curl;
    private $map;

    private $content;
    private $headers;

    public function __construct(\Symfony\Component\HttpFoundation\Request $request, CurlWrapper $curl, $files)
    {
        $this->request  = $request;
		$this->files  = $files;
        $this->curl = $curl;
    }

    public function register($name, $url)
    {
        $this->map[$name] = $url;
    }

    public function run()
    {
        foreach ($this->map as $name => $mapUrl) {
            return $this->dispatch($name, $mapUrl);
        }
    }

    private function dispatch($name, $mapUrl)
    {
        $url = $this->request->getPathInfo();
        if (strpos($url, $name) == 1) {
            $url         = $mapUrl . str_replace("/{$name}", NULL, $url);
            $queryString = $this->request->getQueryString();

            switch ($this->request->getMethod()) {
                case 'GET':
                    $this->content = $this->curl->doGet($url, $this->request, $queryString);
                    break;
                case 'POST':
					if(strstr($url, 'initiate')){
						$this->content = $this->curl->doPost($url, $this->request, NULL, $this->files);
					} else {
                    	$this->content = $this->curl->doPost($url, $this->request, $queryString);
					}
                    break;
                case 'DELETE':
                    $this->content = $this->curl->doDelete($url, $this->request, $queryString);
                    break;
                case 'PUT':
                    $this->content = $this->curl->doPut($url, $this->request, $queryString);
                    break;
            }
            $this->headers = $this->curl->getHeaders();
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getContent()
    {
        return $this->content;
    }
}