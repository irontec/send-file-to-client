<?php
/**
 * En este archivo se declara la clase "SendFileToClient".
 *
 * PHP Version 5+
 *
 * @category Transport
 * @package  SendFileToClient
 * @author   ddniel16 <daniel@irontec.com>
 * @license  https://opensource.org/licenses/EUPL-1.1 European Union Public Licence (V. 1.1)
 * @link     https://github.com/irontec
 */

namespace SendFileToClient;

/**
 * Esta clase en encagar de enviar un archivo grande en partes con el estado:
 * HTTP/1.1 206 Partial Content
 *
 * @category Transport
 * @package  SendFileToClient
 * @author   ddniel16 <daniel@irontec.com>
 * @license  https://opensource.org/licenses/EUPL-1.1 European Union Public Licence (V. 1.1)
 * @link     https://github.com/irontec
 */

class SendFileToClient
{

    /**
     * Procesa el archivo para el envio en partes.
     *
     * @param string $path     ruta al archivo
     * @param string $fileName nombre del archivo
     * @param array  $options  headers adicionales
     *
     * @return Partial file
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
            if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $httpRange, $matches)) {
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
     * Comprueba que el archivo existe
     *
     * @param string $path ruta del archivo
     *
     * @throws \Exception
     *
     * @return NULL
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
     * Obtiene el MimeType del archivo
     *
     * @param string $path rutal del archivo
     *
     * @return string MimeType del archivo
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
