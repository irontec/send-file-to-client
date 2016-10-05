<?php

/**
 * En este archivo se declara la clase "SendFileToClient".
 * @link    https://github.com/ddniel16
 * @author  ddniel16 <dani@irontec.com>
 * @license https://opensource.org/licenses/EUPL-1.1 European Union Public Licence (V. 1.1)
 * @package SendFileToClient\SendFileToClient
 */
namespace SendFileToClient;

/**
 * Esta clase en encagar de enviar un archivo grande en partes con el estado:
 * HTTP/1.1 206 Partial Content
 * @version 0.0.3
 */
class SendFileToClient
{

    /**
     * @param string $path
     * @param string $fileName
     * @param array $options
     */
    public function sendFile($path, $fileName, array $options = array())
    {

        $this->_checkFile($path);
        $contentType = $this->_fileInfo($path);

        $filesize = filesize($path);

        $begin = 0;
        $end   = $filesize - 1;

        $httpRange = (
            isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : false
        );
        if ($httpRange) {

            $matches = array();
            if (
                preg_match(
                    '/bytes=\h*(\d+)-(\d*)[\D.*]?/i',
                    $httpRange,
                    $matches
                )
            ) {

                $begin  = intval($matches[1]);
                if (!empty($matches[2])) {
                    $end = intval($matches[2]);
                }
            }
        }

        if ($begin > 0 || $end < ($filesize - 1)) {
            header('HTTP/1.1 206 Partial Content');
        } else {
            header('HTTP/1.1 200 OK');
        }

        $optionsFilePart = array(
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'X-Pad' => 'avoid browser bug',
            'Pragma' => 'no-cache',
            'Content-Length' => $filesize,
            'Content-Range' => "bytes $begin-$end/$filesize",
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        );

        foreach ($optionsFilePart as $key => $val) {
            $options[$key] = $val;
        }

        if ($end != $filesize) {
            $options['Content-Length'] = ($end - $begin) + 1;
        }

        foreach ($options as $key => $val) {
            header($key . ': ' . $val);
        }

        $fp = fopen($path, 'rb');

        $cur = $begin;
        fseek($fp, $begin, 0);

        while (!feof($fp) && $cur < $end && (connection_status() == 0)) {
            print fread($fp, min(1024 * 16, $end + 1));
            $cur += 1024 * 16;
        }

        fclose($fp);

    }

    /**
     * @param string $path
     * @throws \Exception
     */
    protected function _checkFile($path)
    {

        if (!file_exists($path)) {
            throw new \Exception(
                'File not found',
                404
            );
        }

        $path = realpath($path);

        $fp = fopen($path, 'rb');
        if (!$fp) {
            throw new \Exception(
                'Internal Server Error',
                500
            );
        }

    }

    /**
     * @param string $path
     * @return string
     */
    protected function _fileInfo($path)
    {

        $finfo = new \finfo();
        $contentType = $finfo->file(
            $path,
            FILEINFO_MIME
        );

        return $contentType;

    }
}
