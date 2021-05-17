<?php
/**
 * @author      Laurent Jouanneau
 * @copyright   2018-2019 Laurent Jouanneau
 *
 * @see        http://www.jelix.org
 * @licence    MIT
 */
namespace Jelix\ConsoleHelpers;


use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InteractiveCliHelper
{
    /**
     * @var QuestionHelper
     */
    protected $questionHelper;

    /**
     * @var InputInterface
     */
    protected $consoleInput;

    /**
     * @var OutputInterface
     */
    protected $consoleOutput;


    public function __construct(QuestionHelper $helper, InputInterface $input, OutputInterface $output)
    {
        $this->questionHelper = $helper;
        $this->consoleInput = $input;
        $this->consoleOutput = $output;

        $outputStyle = new OutputFormatterStyle('cyan', 'default');
        $output->getFormatter()->setStyle('question', $outputStyle);
        $output->getErrorOutput()->getFormatter()->setStyle('question', $outputStyle);

        $outputStyle2 = new OutputFormatterStyle('yellow', 'default', array('bold'));
        $output->getFormatter()->setStyle('inputstart', $outputStyle2);
        $output->getErrorOutput()->getFormatter()->setStyle('inputstart', $outputStyle2);
    }

    /**
     * Ask a confirmation.
     *
     * @param string $questionMessage the question
     * @param bool $defaultResponse the default response
     *
     * @return bool true it the user has confirmed
     */
    public function askConfirmation($questionMessage, $defaultResponse = false)
    {
        $questionMessage = "<question>${questionMessage}</question>";
        if (strpos($questionMessage, "\n") !== false) {
            $questionMessage .= "\n";
        }
        $questionMessage .= " ( 'y' or 'n', default is " . ($defaultResponse ? 'y' : 'n') . ')';
        $questionMessage .= '<inputstart> > </inputstart>';
        $question = new ConfirmationQuestion($questionMessage, $defaultResponse);

        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    /**
     * Ask a value to the user.
     *
     * @param string $questionMessage
     * @param false|string $defaultResponse
     * @param false|string[] $autoCompleterValues list of values for autocompletion
     * @param null|callable|array $validator function to validate the value. It accepts
     *                                 a string as parameter, should return the value (may be modified), and
     *                                 should throw an exception when the value is invalid.
     *      Validator may be also an array, with constraint values:
     *          - "required": true or false
     *          - "type" : "number", "string", "float", "integer"
     *          - "regexp": a regular expression
     *
     * @return string the value given by the user
     */
    public function askInformation(
        $questionMessage,
        $defaultResponse = false,
        $autoCompleterValues = false,
        $validator = null
    )
    {
        $questionMessage = "<question>${questionMessage}</question>";
        if ($defaultResponse) {
            if (strpos($questionMessage, "\n") !== false) {
                $questionMessage .= "\n";
            }
            $questionMessage .= " (default is '${defaultResponse}')";
        }
        $questionMessage .= '<inputstart> > </inputstart>';
        $question = new Question($questionMessage, $defaultResponse);
        if (is_array($autoCompleterValues)) {
            $question->setAutocompleterValues($autoCompleterValues);
        }
        $question->setNormalizer(function ($value) {
            // $value can be null here
            return $value ? trim($value) : '';
        });

        $realValidator = null;

        if ($validator) {
            if (is_array($validator) && (isset($validator['required']) || isset($validator['type']) || isset($validator['regexp']))) {
                $validator = array_merge(array('required'=>false, 'type'=>'string', 'regexp'=>''), $validator);
                $realValidator = function ($answer) use ($validator) {
                    if ($validator['required'] && trim($answer) == '') {
                        throw new \Exception('A response is required');
                    }
                    if ($validator['type'] == 'integer' && !preg_match('/^[0-9]+$/', $answer)) {
                        throw new \Exception('The response is not an integer');
                    }
                    if ($validator['type'] == 'float' && !is_numeric($answer)) {
                        throw new \Exception('The response is not a number');
                    }
                    if ($validator['type'] == 'string' && $validator['regexp'] && !preg_match($validator['regexp'], $answer)) {
                        throw new \Exception('Wrong format ('.$validator['regexp'].')');
                    }

                    return $answer;
                };
            }
            else {
                $realValidator = $validator;
            }
        }

        if ($realValidator) {
            $question->setValidator($realValidator);
            $question->setMaxAttempts(10);
        }

        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    /**
     * Ask a hidden value to the user, like a password.
     *
     * @param string $questionMessage
     * @param false|string $defaultResponse
     *
     * @return string the value
     */
    public function askSecretInformation($questionMessage, $defaultResponse = false)
    {
        $questionMessage = "<question>${questionMessage}</question>";
        $questionMessage .= '<inputstart> > </inputstart>';
        $question = new Question($questionMessage, $defaultResponse);
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    /**
     * Ask a value from a choice.
     *
     * @param string $questionMessage
     * @param array $choice list of possible values
     * @param int $defaultResponse the default value
     * @param bool $multipleChoice true if the user can choose different values
     * @param string $errorMessage error message when the user didn't indicate a value from the choice
     *
     * @return string|string[] responses from the user
     */
    public function askInChoice(
        $questionMessage,
        array $choice,
        $defaultResponse = 0,
        $multipleChoice = false,
        $errorMessage = '%s is invalid'
    )
    {
        $questionMessage = "<question>${questionMessage}</question>";
        if (is_array($defaultResponse)) {
            $defaultResponse = implode(',', $defaultResponse);
        }
        if ($defaultResponse !== false) {
            if (strpos($questionMessage, "\n") !== false) {
                $questionMessage .= "\n";
            }
            $questionMessage .= " (default is '${defaultResponse}')";
        }
        $question = new ChoiceQuestion($questionMessage, $choice, $defaultResponse);
        $question->setErrorMessage($errorMessage);
        if ($multipleChoice) {
            $question->setMultiselect(true);
        }

        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }


    public function askList(
        $listTitle,
        $questionItemMessage,
        $values = array()
    )
    {
        $command = '';

        while($command != 'c') {

            // show the list of values
            $this->consoleOutput->writeln('');
            $this->consoleOutput->writeln('<fg=white;options=bold>'.$listTitle.'</>');
            $itemCounts = 0;
            foreach($values as $value) {
                $itemCounts++;
                $this->consoleOutput->writeln('<fg=green>'.$itemCounts . '</><fg=yellow>.</> ' . $value);
            }
            if ($itemCounts == 0) {
                $this->consoleOutput->writeln('  Empty list');
            }
            $this->consoleOutput->writeln('');

            // show the question to ask a command
            $command = $this->askListCommand($itemCounts);
            if ($command == 'a') {
                $value = $this->askListNewItem($questionItemMessage);
                if ($value !== '' && $value !== null) {
                    $values [] = $value;
                }
            }
            else if (is_numeric($command)) {
                $itemNumber = $command;
                $itemCommand = $this->askListItemCommand();
                if ($itemCommand == 'e') {
                    $value = $this->askListExistingItem($questionItemMessage, $values[$itemNumber-1]);
                    if ($value !== '' && $value !== null) {
                        $values [$itemNumber-1] = $value;
                    }
                }
                elseif ($itemCommand == 'd') {
                    array_splice($values, $itemNumber-1, 1);
                }
            }
        }


        return $values;
    }

    private function askListCommand($itemCounts) {
        $message = 'Type ';
        if ($itemCounts) {
            $message .= ' an item number to edit or delete, or ';
        }
        $message .= " '<fg=green>a</>' to add an item, or '<fg=green>c</>' to continue/validate.";
        $this->consoleOutput->writeln($message);
        $questionMessage = '<question>Your choice</question> <inputstart> > </inputstart>';
        $question = new Question($questionMessage);
        $question->setNormalizer(function ($value) {
            // $value can be null here
            return $value ? trim($value) : '';
        });
        $question->setValidator(function ($answer) use($itemCounts) {
            if (is_numeric($answer)) {
                $answer = intval($answer);
                if ($answer < 1 || $answer > $itemCounts) {
                    throw new \Exception('Unknown item number');
                }
            }
            else if ($answer != 'a' && $answer != 'c') {
                throw new \Exception('Unknown command');
            }
            return $answer;
        });
        $question->setMaxAttempts(10);
        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    private function askListNewItem($questionItemMessage)
    {
        $questionMessage = "<question>${questionItemMessage}</question> <inputstart> > </inputstart>";
        $question = new Question($questionMessage);
        $question->setNormalizer(function ($value) {
            // $value can be null here
            return $value ? trim($value) : '';
        });
        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    private function askListItemCommand() {
        $questionMessage = "<question>Do you want to edit (<fg=green>e</>) or to delete (<fg=green>d</>), or return to the list (<fg=green>l</>, default)</question> <inputstart> > </inputstart>";
        $question = new Question($questionMessage, 'l');
        $question->setNormalizer(function ($value) {
            // $value can be null here
            return $value ? trim($value) : '';
        });
        $question->setValidator(function ($answer) {
            if ($answer != 'e' && $answer != 'd' && $answer != 'l') {
                throw new \Exception('Unknown command');
            }
            return $answer;
        });
        $question->setMaxAttempts(10);
        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }

    private function askListExistingItem($questionItemMessage, $value)
    {
        $questionMessage = "<question>${questionItemMessage}</question> <inputstart> > </inputstart>";
        $question = new Question($questionMessage, $value);
        $question->setNormalizer(function ($value) {
            // $value can be null here
            return $value ? trim($value) : '';
        });
        return $this->questionHelper->ask($this->consoleInput, $this->consoleOutput, $question);
    }
}
