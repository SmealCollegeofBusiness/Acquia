<?php

namespace Drupal\acquia_contenthub\Commands\Traits;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Formats output.
 */
trait OutputFormatterTrait {

  /**
   * Prints Table Output.
   *
   * @param string $title
   *   The title of the Table.
   * @param array $headers
   *   The headers of the table.
   * @param array $content
   *   The content of the table.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output to put into table.
   */
  protected function printTableOutput(string $title, array $headers, array $content, OutputInterface $output): void {
    $output->writeln($title);
    $table = new Table($output);
    $table->setHeaders($headers)
      ->setRows($content)
      ->render();
  }

}
