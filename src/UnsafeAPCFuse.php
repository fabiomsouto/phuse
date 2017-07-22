<?php
namespace fabiomsouto\phuse;
use SplObserver;

/**
 * UnsafeAPCFuse
 * This APC-backed fuse works in an unsafe fashion: accesses to APC are not synchronized.
 * This means that PHP processes running in parallel might overwrite values of other processes,
 * causing loss of blow data for example. For high throughput usage, it should not cause a problem.
 *
 * @package fabiomsouto\phuse
 * @author Fabio Souto <fabiomsouto@gmail.com>
 * @license MIT license
 */
class UnsafeAPCFuse implements Fuse
{
    // fuse name, from which we'll build our APC keys
    private $name;
    // number of melts allowed
    private $M;
    // time interval
    private $T;
    // restart interval
    private $R;
    // observers that are interested in the fuse events
    private $observers;

    /**
     * UnsafeAPCFuse constructor.
     *
     * @param $name
     * @param $M
     * @param $T
     * @param $R
     */
    public function __construct($name, $M, $T, $R)
    {
        $this->name = $name;
        $this->M = $M;
        $this->T = $T;
        $this->R = $R;
        $this->observers = [];
    }

    /**
     * Request the fuse to melt.
     * @return bool
     */
    public function melt()
    {
        $now = $this->currentTimeMS();
        $melts = $this->getMeltHistory($this->name);
        $melts[] = $now;
        $newMelts = $this->trimHistory($now, $melts, $this->T);
        $this->saveMeltHistory($this->name, $newMelts);

        // did the fuse blow? the new melt history only contains melts within the time frame so,
        // we can just count them.
        $intensity = count($newMelts);
        if ($intensity > $this->M) {
            // boom.
            $this->meltFuse($this->name, $this->R);
            $this->notify();
        }
    }

    /**
     * Is this fuse OK?
     * @return bool
     */
    public function ok() {
        return !$this->blown();
    }

    /**
     * Is this fuse blown?
     * @return bool
     */
    public function blown()
    {
        $key = "{$this->name}-state";
        $blown = apc_fetch($key) === 'blown';

        // is this fuse blown? if so, let's do some checks.
        if ($blown) {
            $restart_time = apc_fetch("{$this->name}-restart");
            if ($restart_time < $this->currentTimeMS()) {
                $this->enableFuse($this->name);
                $blown = false;
                $this->notify();
            }
        }

        return $blown;
    }

    /**
     * Return this fuse's name.
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the maximum number of melts (restarts) this fuse should tolerate per time-interval.
     * @return integer
     */
    public function getM()
    {
        return $this->M;
    }

    /**
     * Get the time interval (in ms) during which this fuse tolerates, at maximum, the configured blows (restarts).
     * @return integer
     */
    public function getT()
    {
        return $this->T;
    }

    /**
     * Get the annealing time (in ms) for the fuse.
     * The fuse will stay in a blown state for at least this time period, then recover at the next check.
     * @return integer
     */
    public function getR()
    {
        return $this->R;
    }

    /**
     * Return the current time in milliseconds.
     * @return integer
     */
    private function currentTimeMS() {
        return (int) round(microtime(true) * 1000);
    }

    /**
     * Fetch the fuse melt history from APC.
     * @param name
     * @return array
     */
    private function getMeltHistory($name) {
        $key = "$name-melt-history";
        return apc_fetch($key) ?: [];
    }

    /**
     * Save the fuse melt history in APC.
     * @param $name
     * @param $melts
     * @return array|bool
     */
    private function saveMeltHistory($name, $melts) {
        $key = "$name-melt-history";
        return apc_store($key, $melts);
    }

    /**
     * Delete the fuse melt history in APC.
     * @param name
     * @return array|bool
     */
    private function deleteMeltHistory($name) {
        $key = "$name-melt-history";
        return apc_delete($key);
    }

    /**
     * Trim the melt history. It will only contain melt events
     * that fit within the time window $T counting from $now.
     * @param $now
     * @param $history
     * @param $T
     * @return array An array containing the timestamps, in milliseconds, for each melt event.
     */
    private function trimHistory($now, $history, $T) {
        $newHistory = [];
        foreach ($history as $ts) {
            if ($now - $ts < $T) {
                $newHistory[] = $ts;
            }
        }
        return $newHistory;
    }

    /**
     * Melt this fuse NOW.
     * @param $name
     * @param $restart
     */
    private function meltFuse($name, $restart) {
        apc_store("$name-state", "blown");
        apc_store("$name-restart", $this->currentTimeMS() + $restart);
    }

    /**
     * Enable this fuse NOW.
     * @param $name
     */
    private function enableFuse($name) {
        apc_store("$name-state", "ok");
        apc_delete("$name-restart");
        $this->deleteMeltHistory($name);
    }

    /**
     * Attach an SplObserver
     * @link http://php.net/manual/en/splsubject.attach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to attach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function attach(SplObserver $observer)
    {
        $this->observers[] = $observer;
    }

    /**
     * Detach an observer
     * @link http://php.net/manual/en/splsubject.detach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to detach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function detach(SplObserver $observer)
    {
        $key = array_search($observer,$this->observers, true);
        if($key){
            unset($this->observers[$key]);
        }
    }

    /**
     * Notify an observer
     * @link http://php.net/manual/en/splsubject.notify.php
     * @return void
     * @since 5.1.0
     */
    public function notify()
    {
        foreach($this->observers as $observer) {
            $observer->update($this);
        }
    }
}