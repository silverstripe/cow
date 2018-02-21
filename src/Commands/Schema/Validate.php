<?php

namespace SilverStripe\Cow\Commands\Schema;

use Exception;
use JsonSchema\Validator;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Utility\Config;
use SilverStripe\Cow\Utility\SchemaValidator;

/**
 * Validates the cow configuration file against the cow schema
 */
class Validate extends Command
{
    protected $name = 'schema:validate';

    protected $description = 'Validate the cow configuration file';

    /**
     * Get the contents of the cow configuration file in the current directory
     *
     * @return array
     * @throws Exception
     */
    protected function getCowData()
    {
        $directory = getcwd();
        $cowFile = $directory . '/.cow.json';
        if (!file_exists($cowFile)) {
            throw new Exception('.cow.json does not exist in current directory');
        }

        return Config::parseContent(file_get_contents($cowFile), false);
    }

    /**
     * Validate the current directory's cow configuration file against the schema
     *
     * @return int Process exit code
     * @throws Exception
     */
    protected function fire()
    {
        $cowData = $this->getCowData();

        /** @var Validator $validator */
        $validator = SchemaValidator::validate($cowData);

        if ($validator->isValid()) {
            $this->output->writeln('<info>Cow schema is valid!</info>');
            return 0;
        }

        $this->output->writeln('<info>Cow schema failures:</info>');
        foreach ($validator->getErrors() as $error) {
            $this->output->writeln('<error>' . $error['property'] . ': ' . $error['message'] . '</error>');
        }
        return 1;
    }

    protected function configureOptions()
    {
        // noop
    }
}
