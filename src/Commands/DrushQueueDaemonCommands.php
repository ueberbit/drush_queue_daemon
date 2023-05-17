<?php

namespace Drupal\drush_queue_daemon\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Drush\Commands\DrushCommands;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Symfony\Component\Process\Process;

/**
 * A Drush command file.
 */
class DrushQueueDaemonCommands extends DrushCommands implements ProcessManagerAwareInterface, SiteAliasManagerAwareInterface {

  use ProcessManagerAwareTrait;
  use SiteAliasManagerAwareTrait;

  /**
   * Run a specific queue by name.
   *
   * @command queue:run:daemon
   *
   * @param string $name The name of the queue to run, as defined in either hook_queue_info or hook_cron_queue_info.
   * @validate-queue name
   * @option time-limit The maximum number of seconds allowed to run the queue.
   * @option items-limit The maximum number of items allowed to run the queue.
   * @option lease-time The maximum number of seconds that an item remains claimed.
   * @option interval Seconds to sleep before asking for new queue items after no queue items were found.
   * @option timeout The time limit in seconds the loop can run.
   */
  public function run(string $name, $options = ['time-limit' => self::REQ, 'items-limit' => self::REQ, 'lease-time' => self::REQ, 'interval' => self::REQ, 'timeout' => self::REQ]): void {
    $aliasRecord = $this->siteAliasManager()->getSelf();
    $queueOptions = [
      'time-limit' => NULL,
      'items-limit' => NULL,
      'lease-time'  => NULL,
    ];
    foreach ($queueOptions as $k => $v) {
      if (isset($options[$k])) {
        $queueOptions[$k] = $options[$k];
      }
      else {
        unset($queueOptions[$k]);
      }
    }
    $queue = \Drupal::queue($name, TRUE);
    $timeout = $options['timeout'] ?? 60;
    $interval = $options['interval'] ?? 1;
    $startTime = time();
    Loop::addPeriodicTimer($interval, function (TimerInterface $timer) use ($queue, $queueOptions, $aliasRecord, $name, $startTime, $timeout) {
      if ($timeout && $startTime + $timeout < time()) {
        Loop::get()->cancelTimer($timer);
      }
      if ($queue->numberOfItems() > 0) {
        /** @var \Symfony\Component\Process\Process $process */
        $process = $this->processManager()->drush($aliasRecord, 'queue:run', [$name], $queueOptions);
        $process->mustRun(function ($type, $buffer) {
          if ($type === Process::ERR) {
            $this->stderr()->write($buffer);
          }
          else {
            $this->output()->write($buffer);
          }
        });
      }
    });
  }

}
