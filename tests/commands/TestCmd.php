<?php


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Jelix\ConsoleHelpers\InteractiveCliHelper;

class TestCmd extends Command
{
    protected function configure()
    {
        $this
            ->setName('test');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {

        $questionHelper = $this->getHelper('question');
        $interactive = new InteractiveCliHelper($questionHelper, $input, $output);

        if ($interactive->askConfirmation("confirm or not this question ?")) {
            $output->writeln("Confirmed");
        }
        else {
            $output->writeln("Not confirmed");
        }


        $result = $interactive->askInformation("Your firstname");
        $output->writeln("Your firstname is ".$result);

        $result = $interactive->askInformation("A country name", "France");
        $output->writeln("The country is ".$result);

        $result = $interactive->askInformation("A city name (autocompletion)", "Paris", ["Paris", "London", "Washingtown", "Berlin"]);
        $output->writeln("The city is ".$result);

        $result = $interactive->askInChoice("A fruit", ["Banana", "Apple", "Orange", "Pineapple"], 2);
        $output->writeln("The fruit is ".$result);

        $result = $interactive->askSecretInformation("A secret information");
        $output->writeln("The secret is ".$result);


        $results = $interactive->askList("Your preferred car models", "name of car",
            /*[
                "a"=> "Renault",
                "b"=> "Ford",
                "c"=> "Mercedes",
                "d"=> "Ferrari",
                "e"=> "BMW",
                "f"=> "Dodge"
            ]*/
        );

        foreach($results as $result) {
            $output->writeln("choice is ".$result);
        }


        return 0;
    }
}
