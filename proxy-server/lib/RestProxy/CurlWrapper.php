<?php
namespace RestProxy;

class CurlWrapper
{
    const HTTP_OK = 200;
	
	public function preparePostFields($array) {
		$params = array();
		
		foreach ($array as $key => $value) {
			$params[] = $key . '=' . urlencode($value);
		}
		
		return implode('&', $params);
	}

    public function doGet($url, $request, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, is_null($queryString) ? $url : $url . '?' . $queryString);

        return $this->doMethod($s, $request);
    }

    public function doPost($url, $request, $queryString = NULL, $files = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_POST, TRUE);
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        } else {
			if(!is_null($files) && !empty($files)){
				if(isset($files['Filedata'])){ // requests sent from Flash Player
					$files['file'] = $files['Filedata'];
				}
				$files['file']['name'] = isset($files["file"]["name"]) && strstr($files["file"]["name"], '.') ? $files["file"]["name"] : $_POST['name'];
				$data = array(
					'name'	=>	str_replace(' ', '-', $files['file']['name']),
					'file'	=>	'@' . $files['file']['tmp_name']
				);
				curl_setopt($s, CURLOPT_POSTFIELDS, $data);
			} else {
				curl_setopt($s, CURLOPT_POSTFIELDS, $request->getContent());
			}
		}
        return $this->doMethod($s, $request);
    }

    public function doPut($url, $request, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, $url);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'PUT');
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        } else {
			curl_setopt($s, CURLOPT_POSTFIELDS, $request->getContent());	
		}
        return $this->doMethod($s, $request);
    }

    public function doDelete($url, $request, $queryString = NULL)
    {
        $s = curl_init();
        curl_setopt($s, CURLOPT_URL, is_null($queryString) ? $url : $url . '?' . $queryString);
        curl_setopt($s, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!is_null($queryString)) {
            curl_setopt($s, CURLOPT_POSTFIELDS, parse_str($queryString));
        } else {
			curl_setopt($s, CURLOPT_POSTFIELDS, $request->getContent());	
		}
        return $this->doMethod($s, $request);
    }

    private $responseHeaders = array();
    private $status;

    private function doMethod($s, $request)
    {
		$headers = $request->headers;
        curl_setopt($s, CURLOPT_HEADER, TRUE);
        curl_setopt($s, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($s, CURLOPT_HTTPHEADER, array('X-Mashape-Authorization: ' . $headers->get('x-mashape-authorization'), 'X-Mashape-User: ' . $headers->get('x-mashape-user')));
        $out                   = curl_exec($s);	
        $this->status          = curl_getinfo($s, CURLINFO_HTTP_CODE);
        $this->responseHeaders = curl_getinfo($s, CURLINFO_HEADER_OUT);
        curl_close($s);
		
		// check if HTTP/1.1 100 Continue is present
		// and remove it if it is
		$parts = explode("\r\n\r\n", $out);
		if(count($parts) > 2){
			$continue = array_shift($parts);
		}
		$out = implode("\r\n\r\n", $parts);
		
        list($this->responseHeaders, $content) = $this->decodeOut($out);
        if ($this->status != self::HTTP_OK) {
			list($this->responseHeaders, $content) = explode("\r\n\r\n", $out, 2);
            throw new \Exception($content, $this->status);
        }
        return $content;
    }

    private function decodeOut($out)
    {
        // It should be a fancy way to do that :(
        $headersFinished = FALSE;
        $headers         = $content = array();
        $data            = explode("\n", $out);
        foreach ($data as $line) {
            if (trim($line) == '') {
                $headersFinished = TRUE;
            } else {
                if ($headersFinished === FALSE && strpos($line, ':') > 0) {
                    //list($key, $value) = explode(": ", $line, 2);
                    //$headers[$key] = $value;
                    $headers[] = $line;
                }

                if ($headersFinished) {
                    $content[] = $line;
                }
            }
        }
        return array($headers, implode("\n", $content));
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getHeaders()
    {
        return $this->responseHeaders;
    }
}