<?php

/**
 * Handles all SQL migration scripts. This file is designed
 * to read in the SQL scripts from the /db directory and run
 * each sequentially. If the filename exists in our migrations
 * table (unless it's script 0 to create this table) then skip
 * that script.
 */

namespace App\Model;

use Fn;
use DateTime;
use App\Model;
use Pb\PDO\Database;
use App\Traits\Model as ModelTrait;

class Migration extends Model
{
    use ModelTrait;

    public $name;
    public $created_at;

    /**
     * Read each file in the script folder and check to see
     * if it's been run before. If so, skip it. If not, run
     * the script and log it in the migrations table.
     *
     * @return bool     
     */
    public function run()
    {
        $this->cli()->info('Running SQL migration scripts');

        foreach (glob(DBSCRIPTS) as $filename) {
            $script = basename($filename, '.sql');
            $queries = explode("\n\n", file_get_contents($filename));

            if ($this->isRunAlready($script)) {
                $this->cli()->dim("[skip] {$script}.sql");
                continue;
            }

            $this->cli()->inline("[....] Running {$script}.sql");

            foreach ($queries as $query) {
                if (! $this->db()->query($query)) {
                    $this->cli()
                        ->inline("\r[")
                        ->redInline('fail')
                        ->inline("] Running {$script}.sql")
                        ->br()
                        ->br()
                        ->error($this->getError());

                    return false;
                }
            }

            $this->markRun($script);
            $this->cli()
                ->inline("\r[")
                ->greenInline(' ok ')
                ->inline("] Running {$script}.sql")
                ->br();
        }

        return true;
    }

    public function setMaxAllowedPacket(int $mb = 16)
    {
        $value = $this->getMaxAllowedPacket();
        $newSize = (int) ($mb * 1024 * 1024);

        if (! $value || $value < $newSize) {
            $this->db()->query(
                "SET GLOBAL max_allowed_packet = $newSize;"
            );

            return false;
        }

        return true;
    }

    /**
     * Returns the max_allowed_packet setting. This takes an optional
     * $db argument to use as the database connection if this query
     * is run outside of the Model scope.
     *
     * @param Database $db Optional database connection
     *
     * @return int
     */
    public function getMaxAllowedPacket(Database $db = null)
    {
        $db = $db ?: $this->db();
        $size = $db->query("SHOW VARIABLES LIKE 'max_allowed_packet';")->fetch();

        return Fn\get($size, 'Value');
    }

    /**
     * Checks if the script was already run.
     *
     * @param string $script Filename for the script
     */
    private function isRunAlready(string $script)
    {
        $migrationsExists = $this->db()
            ->query("show tables like 'migrations';")
            ->fetch();

        if (! $migrationsExists) {
            return false;
        }

        return $this->db()
            ->select()
            ->from('migrations')
            ->where('name', '=', $script)
            ->execute()
            ->fetchObject($this->getClass());
    }

    /**
     * Marks a script as 'run'.
     *
     * @param string $script Filename for the script
     */
    private function markRun(string $script)
    {
        $createdAt = new DateTime;

        return $this->db()
            ->insert(['name', 'created_at'])
            ->into('migrations')
            ->values([
                $script,
                $createdAt->format(DATE_DATABASE)
            ])->execute();
    }
}
