<?php

declare(strict_types=1);

namespace MauticPlugin\SmtpingBundle\Command;

use MauticPlugin\SmtpingBundle\Api\SmtpingClient;
use MauticPlugin\SmtpingBundle\Api\SmtpingException;
use MauticPlugin\SmtpingBundle\Service\ContactFields;
use MauticPlugin\SmtpingBundle\Service\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bin/console mautic:smtping:verify [--limit=5000] [--segment=ID] [--older-than=DAYS]
 */
class VerifyContactsCommand extends Command
{
    public function __construct(private Settings $settings, private ContactFields $fields)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mautic:smtping:verify')
            ->setDescription('Verify contact email addresses with SMTPing and store status, band and date on each contact.')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum contacts per run (max 100000)', '5000')
            ->addOption('segment', 's', InputOption::VALUE_REQUIRED, 'Only contacts in this segment ID')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Re-verify contacts last checked more than N days ago')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Maximum wait for a bulk job, in seconds', '1800');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->settings->isEnabled()) {
            $output->writeln('<error>Configure and publish SMTPing Email Verifier in Settings > Plugins first.</error>');

            return Command::FAILURE;
        }

        $created = $this->fields->ensure();
        if ($created) {
            $output->writeln('Created contact fields: '.implode(', ', $created));
        }
        if (!$this->fields->columnsReady()) {
            $output->writeln('<comment>The SMTPing fields are queued for creation. Run php bin/console mautic:custom-field:create-column, then run this command again.</comment>');

            return Command::FAILURE;
        }

        $limit     = max(1, min(SmtpingClient::BULK_MAX, (int) $input->getOption('limit')));
        $segment   = null !== $input->getOption('segment') ? (int) $input->getOption('segment') : null;
        $olderThan = null !== $input->getOption('older-than') ? max(1, (int) $input->getOption('older-than')) : null;
        $contacts  = $this->fields->pending($limit, $segment, $olderThan);

        if (!$contacts) {
            $output->writeln('Nothing to verify.');

            return Command::SUCCESS;
        }
        $output->writeln(sprintf('Verifying %d contacts...', count($contacts)));

        try {
            $counts = count($contacts) <= 10
                ? $this->single($this->settings->client(), $contacts)
                : $this->bulk($this->settings->client(), $contacts, (int) $input->getOption('timeout'), $output);
        } catch (SmtpingException $e) {
            $output->writeln('<error>SMTPing: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Done. safe: %d, judgement: %d, avoid: %d', $counts['safe'], $counts['judgement'], $counts['avoid']));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array{id: int, email: string}> $contacts
     *
     * @return array<string, int>
     */
    private function single(SmtpingClient $client, array $contacts): array
    {
        $counts = ['safe' => 0, 'judgement' => 0, 'avoid' => 0];
        foreach ($contacts as $c) {
            $r = $client->verify($c['email']);
            $this->fields->write($c['id'], (string) ($r['status'] ?? 'unknown'), $r['band']);
            ++$counts[$r['band']];
        }

        return $counts;
    }

    /**
     * @param array<int, array{id: int, email: string}> $contacts
     *
     * @return array<string, int>
     */
    private function bulk(SmtpingClient $client, array $contacts, int $timeout, OutputInterface $output): array
    {
        $byEmail = [];
        foreach ($contacts as $c) {
            $byEmail[strtolower(trim($c['email']))][] = $c['id'];
        }

        $job   = $client->createBulk(array_keys($byEmail));
        $jobId = (string) ($job['jobId'] ?? '');
        if ('' === $jobId) {
            throw new SmtpingException('The API did not return a job id.');
        }
        $output->writeln('Bulk job '.$jobId.' created.');

        $deadline = time() + max(60, $timeout);
        $delay    = 5;
        while (true) {
            $state  = $client->getBulk($jobId);
            $status = strtolower((string) ($state['status'] ?? ''));
            if ('succeeded' === $status) {
                break;
            }
            if (in_array($status, ['failed', 'cancelled'], true)) {
                throw new SmtpingException('Job '.$jobId.' '.$status.'.');
            }
            if (time() + $delay > $deadline) {
                throw new SmtpingException('Job '.$jobId.' still running. Run the command again later: already verified contacts are skipped.');
            }
            $output->writeln(sprintf('  %d / %d processed', (int) ($state['processedEmails'] ?? 0), count($byEmail)));
            sleep($delay);
            $delay = min(30, (int) ceil($delay * 1.5));
        }

        $counts = ['safe' => 0, 'judgement' => 0, 'avoid' => 0];
        foreach ($client->bulkResults($jobId) as $row) {
            $email  = strtolower(trim((string) ($row['email'] ?? '')));
            $status = (string) ($row['status'] ?? 'unknown');
            $band   = SmtpingClient::band($status);
            foreach ($byEmail[$email] ?? [] as $id) {
                $this->fields->write($id, $status, $band);
                ++$counts[$band];
            }
        }

        return $counts;
    }
}
