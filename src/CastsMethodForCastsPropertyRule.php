<?php declare(strict_types=1);

namespace DressCodeRules\Laravel;

use DressCode\Analyses\Types;
use DressCode\{Decision, Domain, NodeRule, RuleContext, RuleInfo, Stage, Tristate};
use Illuminate;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Builder, Node, Printer, Token, Trivia, Visibility};
use PhpSyntax\Nodes\Expression\{ArrayNode, PropertyFetchNode, VariableNode};
use PhpSyntax\Nodes\Member\{MethodNode, PropertyNode};
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use function count;


/**
 * The casts of a model returned by its method casts(), which Laravel 11 reads beside the property $casts and writes
 * so in its skeleton: the array of the property becomes the value the method returns, and the @var of its doc comment
 * a @return.
 *
 * Reported and left: a property declared together with others, static or with no array, a class that declares casts()
 * already or reads $this->casts itself. Without the types only a class extending Model by name is known.
 */
#[RuleInfo(Stage::Structure, modifiesComments: true, requires: ['laravel/framework' => '>=11.0'])]
final class CastsMethodForCastsPropertyRule extends NodeRule
{
	private const Model = Illuminate\Database\Eloquent\Model::class;


	public static function getDecisions(): array
	{
		return [new Decision('laravel.castsMethod', Domain::adopted(), 'The casts of a model returned from its method `casts()` for the property `$casts`')];
	}


	public function getVisitedNodes(): array
	{
		return [ClassNode::class];
	}


	public function enter(Node|Token $node, RuleContext $context): void
	{
		if (!$node instanceof ClassNode) {
			return;
		}

		$property = null;
		foreach ($node->members as $member) {
			if ($member instanceof PropertyNode && array_any($member->items->getItems(), fn($item) => $item->plainName === 'casts')) {
				$property = $member;
			}
		}

		if ($property === null || !self::isModel($node, $context)) {
			return;
		}

		$items = $property->items->getItems();
		$default = $items[0]->default;
		$message = 'Property `' . self::Model . '::$casts` is replaced by the method `casts()`';
		$refusal = match (true) {
			count($items) > 1 => ', but it is declared together with other properties',
			$property->modifiers->static || !$default instanceof ArrayNode => ', but it holds no array to return',
			array_any($node->members->getItems(), fn($member) => $member instanceof MethodNode && strcasecmp($member->name->text, 'casts') === 0)
				=> ', but the class declares `casts()` already',
			$node->findFirst(PropertyFetchNode::class, fn(PropertyFetchNode $fetch) => $fetch->plainName === 'casts'
				&& $fetch->object instanceof VariableNode && $fetch->object->isThis()) !== null => ', but the class reads it itself',
			default => null,
		};
		if ($refusal !== null) {
			$context->report($items[0], $message . $refusal . '.', fixable: false);
			return;
		}

		if (!$context->report($items[0], $message . '.')) {
			return;
		}

		$style = $context->style;
		$indentation = $property->getFirstToken()?->getIndentation() ?? $style->indent;
		$array = str_replace("\n", "\n" . $style->indent, Printer::print($default->withoutEdgeTrivia()));
		$method = (new Builder)->fragment(
			MethodNode::class,
			($property->modifiers->visibility === Visibility::Public ? 'public' : 'protected') . ' function casts(): array' . $style->lineEnding
			. $indentation . '{' . $style->lineEnding
			. $indentation . $style->indent . 'return ' . $array . ';' . $style->lineEnding
			. $indentation . '}',
		);
		$property->replaceWith($method);
		$first = $method->getFirstToken();
		$first->setLeadingTrivia(array_map(
			fn(Trivia $trivia) => $trivia->is(Trivia::DocComment)
				? $trivia->withText((string) preg_replace('~@var(?=\s)~', '@return', $trivia->text))
				: $trivia,
			$first->leadingTrivia,
		));
	}


	/** Whether the class is a model: by its types, or without them by the parent it names. */
	private static function isModel(ClassNode $class, RuleContext $context): bool
	{
		$resolver = $context->getAnalysis(NameResolver::class);
		$name = ltrim($resolver->getNamespace($class) . '\\' . $class->name->text, '\\');
		$types = $context->findAnalysis(Types::class);
		return $types !== null
			? $types->isSubtype($name, self::Model) === Tristate::Yes && strcasecmp($name, self::Model) !== 0
			: $class->extends instanceof NameNode && strcasecmp($resolver->resolveClass($class->extends), self::Model) === 0;
	}
}
