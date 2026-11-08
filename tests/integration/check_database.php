<?php
// Refuse to install over an existing database or an arbitrary database name.
$name = getenv('ANTISPAM_TEST_DB_NAME');
if (!is_string($name) || !preg_match('/^antispam_(test|integration)(_[a-z0-9]+)?$/D', $name))
{
    fwrite(STDERR, "Use a disposable antispam_test or antispam_integration database.\n");
    exit(1);
}
try
{
    $db = new mysqli(getenv('ANTISPAM_TEST_DB_HOST'), getenv('ANTISPAM_TEST_DB_USER'),
        getenv('ANTISPAM_TEST_DB_PASSWORD') ?: '', $name, (int) getenv('ANTISPAM_TEST_DB_PORT'));
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $statement->bind_param('s', $name);
    $statement->execute();
    $statement->bind_result($tables);
    $statement->fetch();
    if ($tables !== 0)
    {
        fwrite(STDERR, "Refusing to install: integration database already contains tables. Use a fresh disposable database.\n");
        exit(1);
    }
    echo "Empty disposable database verified.\n";
}
catch (Throwable $error)
{
    fwrite(STDERR, "Cannot connect to disposable integration database (code " . (int) $error->getCode() . ").\n");
    exit(1);
}
