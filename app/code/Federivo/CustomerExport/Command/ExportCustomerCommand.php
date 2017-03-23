<?php

namespace Federivo\CustomerExport\Command;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCustomerCommand extends Command
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var Csv
     */
    private $csvHandler;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
     * @var File
     */
    private $fileHandler;


    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Csv $csvHandler,
        DirectoryList $directoryList,
        File $fileHandler,
        $name = null
    )
    {
        $this->customerRepository = $customerRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($name);
        $this->csvHandler = $csvHandler;
        $this->directoryList = $directoryList;
        $this->fileHandler = $fileHandler;
    }

    protected function configure()
    {
        $options = [
            new InputOption(
                "output",
                null,
                InputOption::VALUE_OPTIONAL,
                "Output format for the customer export file"
            ),
        ];

        $this
            ->setName("federivo:export-customers")
            ->setDescription('Export customer data. Use --output="csv" or --output="json" for configuring the output format.')
            ->setDefinition($options);
    }

    /***
     * Get the list of customers and write the export file according to the output format specified
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchResults = $this->customerRepository->getList($searchCriteria);

        $outputArray = [];

        /** @var \Magento\Customer\Model\Data\Customer $customer */
        foreach ($searchResults->getItems() as $customer) {
            $name = "{$customer->getFirstname()} {$customer->getLastname()}";
            $outputArray[] = [$name, $customer->getEmail()];
        }

        $filename = $this->getFilename();

        //if the output selected is "json" export as json format.
        if($input->getOption("output") == "json") {
            $filename = $filename . ".json";
            $this->fileHandler->filePutContents($filename, json_encode($outputArray));
        }
        else {
            //if not export as csv
            $filename = $filename . ".csv";
            $this->csvHandler->saveData($filename, $outputArray);
        }

        $output->writeln("[INFO] Export finished. Export file generated: {$filename}");
    }

    /***
     * Return filename with timestamp
     * @return string
     */
    protected function getFilename() {
        $date = new \DateTime();
        $dateLabel = $date->format('YmdHis');
        $exportPath = $this->directoryList->getPath('var') . "/export/";
        if(!$this->fileHandler->isDirectory($exportPath)) {
            $this->fileHandler->createDirectory($exportPath);
        }
        return "{$exportPath}customers-{$dateLabel}";
    }
}