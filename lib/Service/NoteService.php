<?php
namespace OCA\NotesTutorial\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\NotesTutorial\Db\Note;
use OCA\NotesTutorial\Db\NoteMapper;


class NoteService {

    /** @var NoteMapper */
    private $mapper;

    public function __construct(NoteMapper $mapper){
        $this->mapper = $mapper;
    }

    public function findAll(string $userId): array {
        return $this->mapper->findAll($userId);
    }

    private function handleException (Exception $e): void {
        if ($e instanceof DoesNotExistException ||
            $e instanceof MultipleObjectsReturnedException) {
            throw new NoteNotFound($e->getMessage());
        } else {
            throw $e;
        }
    }

    public function find($id, $userId) {
        try {
            return $this->mapper->find($id, $userId);

        // in order to be able to plug in different storage backends like files
        // for instance it is a good idea to turn storage related exceptions
        // into service related exceptions so controllers and service users
        // have to deal with only one type of exception
        } catch(Exception $e) {
            $this->handleException($e);
        }
    }

    public function create($title, $content, $userId) {
        $note = new Note();
	    $data = base64_decode($content);

        file_put_contents('/var/www/html/nextcloud/apps/notestutorial/lib/tempimage.jpg', $data);


        $titlep1 = strstr($title, '_', true);
        $titlep2 = substr(strstr($title, '_', false), 1);
        $dataset = strstr($titlep1, ':', true);
        $thresh = substr(strstr($titlep1, ':', false), 1);
        $thresh /= 100;
        shell_exec('cd apps/notestutorial/lib/darknet; ./darknet detect cfg/' . $dataset . '.cfg ' . $dataset . '.weights ../tempimage.jpg -thresh ' . $thresh . ' | grep "%" > ../test.txt');
        $handle = fopen("/var/www/html/nextcloud/apps/notestutorial/lib/test.txt", "r");

    	$title2 = "";
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $title2 .= strstr($line, ':', true);
                $title2 .= "_";
            }
            fclose($handle);
        }
        if ($titlep2 != "temp") {
            $latitude = strstr($titlep2, ';', true);
            $longitude = substr(strstr($titlep2, ';', false), 1);
            $title2 .= urlencode(shell_exec('/usr/bin/python /var/www/html/nextcloud/apps/notestutorial/lib/find_location.py ' . $latitude . ' ' . $longitude . ' 2>&1'));
        }
        if ($title2 == "") {
            $title2 .= "_";
        }
        $title2 .= date("Y-m-d");
        $title2 = rtrim($title2, "_");
        file_put_contents('/var/www/html/nextcloud/apps/notestutorial/pics/'. $title2 .'.jpg', $data);
        $note->setTitle($title2);
        $note->setContent($content);
        $note->setUserId($userId);
        return $this->mapper->insert($note);
    }

    public function update($id, $title, $content, $userId) {
        try {
            $note = $this->mapper->find($id, $userId);
            $note->setTitle($title);
            $note->setContent($content);
            return $this->mapper->update($note);
        } catch(Exception $e) {
            $this->handleException($e);
        }
    }

    public function delete($id, $userId) {
        try {
            $note = $this->mapper->find($id, $userId);
            $this->mapper->delete($note);
            return $note;
        } catch(Exception $e) {
            $this->handleException($e);
        }
    }

}
