<?php
/**
 * Copyright © 2023 Anton Abramchenko. All rights reserved.
 *
 * Redistribution and use permitted under the BSD-3-Clause license.
 * For full details, see COPYING.txt.
 *
 * @author    Anton Abramchenko <anton.abramchenko@labofgood.com>
 * @copyright 2023 Anton Abramchenko
 * @license   See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace App\Command;

use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Class RectorOutputToPatch
 *
 * Generate composer patches for each file in Rector json output.
 */
class RectorOutputToPatch extends Command
{
    /**
     * List of command arguments
     */
    private const OPT_FILE_PATH = 'file_path';
    private const OPT_TICKET = 'ticket';
    private const OPT_OUTPUT_DIR = 'output_dir';

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('rector:generate:composer-patches')
            ->setDescription('Generate composer patches for each file in json output')
            ->addOption(
                self::OPT_FILE_PATH,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the file which contains the JSON output'
            )->addOption(
                self::OPT_TICKET,
                null,
                InputOption::VALUE_OPTIONAL,
                'Identifier of the ticket in Jira or Github ect.',
                'identifier-not-set'
            )->addOption(
                self::OPT_OUTPUT_DIR,
                null,
                InputOption::VALUE_OPTIONAL,
                'path to the output directory',
                BP . DS . 'patches'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getOption(self::OPT_FILE_PATH);
        $ticketName = $input->getOption(self::OPT_TICKET);
        $outputDir = $input->getOption(self::OPT_OUTPUT_DIR);

        $styleIo = new SymfonyStyle($input, $output);

        try {
            $preparedFiles = $this->generatePatchContent($filePath, $ticketName);
            $outputText = 'Here is a list of the patches that have been generated: ';
            $outputList = [];

            foreach ($preparedFiles as $file => $diff) {
                $patchPath = $outputDir . DS . $file;
                file_put_contents($patchPath, $diff);
                $outputList[] = $patchPath;
            }

            $styleIo->success($outputText);
            $styleIo->listing($outputList);

            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $styleIo->error($exception->getMessage());
            $styleIo->error($exception->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Generate composer patches for each file in json output
     *
     * @param string $filePath
     * @param string $ticketName
     *
     * @return array<string, string>
     * @throws RuntimeException
     */
    private function generatePatchContent(string $filePath, string $ticketName): array
    {
        try {
            $parsedContent = json_decode(
                file_get_contents(realpath($filePath)),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Cannot parse JSON output: ' . $e->getMessage());
        }

        if (!is_array($parsedContent)) {
            throw new RuntimeException('Invalid JSON structure.');
        }

        if (!array_key_exists('file_diffs', $parsedContent)) {
            throw new RuntimeException('The JSON output does not contain file_diffs');
        }

        $result = [];

        foreach ($parsedContent['file_diffs'] as $patchInfo) {
            $diff = $patchInfo['diff'];
            $file = $patchInfo['file'];
            preg_match('/vendor\/([\w-]+\/[\w.-]+)/', (string) $file, $matches);
            $patchName = $this->generatePatchName($file, $ticketName);
            $diff = preg_replace(['/(---) Original/', '/(\+\+\+) New/'], '\1 ' . $patchName, $diff);

            $result[$patchName] = sprintf(
                "@package %s\n@ticket %s\n%s",
                $matches[1],
                $ticketName,
                $diff
            );
        }

        return $result;
    }

    /**
     * Generate patch name based on patched file name and ticket name.
     *
     * @param string $file
     * @param string $ticketName
     *
     * @return string
     */
    private function generatePatchName(string $file, string $ticketName): string
    {
        return $ticketName . '-' . str_replace(['vendor/', '/'], ['', '_'], $file) . '.patch';
    }
}
