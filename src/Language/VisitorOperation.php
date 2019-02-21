<?hh //strict

namespace GraphQL\Language;

class VisitorOperation
{
    public bool $doBreak = false;

    public bool $doContinue = false;

    public bool $removeNode = false;
}
