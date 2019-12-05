
Library providing helpers for Symfony Console




# installation

You can install it from Composer. In your project:

```
composer require "jelix/console-helper"
```

# usage

In your command class:

```php
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $questionHelper = $this->getHelper('question');
        $interactive = new InteractiveCliHelper($questionHelper, $input, $output);

        $interactive->...
    }

```