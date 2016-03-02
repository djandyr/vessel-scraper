<?

namespace WarrantGroup\VesselScraper\Csv;

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

class Writer {

    protected $csv;
    protected $filename = 'vessel.csv';
    protected $filepath;
    protected $columns = array('imo', 'mmsi', 'name', 'flag', 'type');

    /**
     * Create CSV file
     *
     */
    public function __construct()
    {
        if(empty($filepath)) {
            $this->filepath = realpath(dirname(__FILE__) . '/../') . '/' . $this->filename;
        }

        $this->csv = \League\Csv\Writer::createFromPath(new \SplFileObject($this->filepath, 'w+'), 'w');
        $this->csv->insertOne($this->columns);
    }

    /**
     * @param $rows
     */
    public function write($rows) {
        if(count($rows) > 0) {
            $this->csv->insertAll($rows);
        }
    }

    public function setFilePath($filepath) {
        $this->filepath = $filepath;
    }

    public function getFilePath() {
        return $this->filepath;
    }
}