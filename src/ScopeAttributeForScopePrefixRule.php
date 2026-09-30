<?php declare(strict_types=1);

namespace DressCode\Laravel;

use DressCode\Analyses\Types;
use DressCode\{NodeRule, Risk, RuleContext, RuleGroup, RuleInfo, Stage, Tristate};
use DressCode\Rules\CodeWriter;
use Illuminate;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Node, Token, Trivia, Visibility};
use PhpSyntax\Nodes\Expression\{MethodCallNode, StaticMethodCallNode};
use PhpSyntax\Nodes\{IdentifierNode, NameNode};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;


/**
 * A local scope of a model marked by the attribute Scope, which Laravel 12.4 reads beside the prefix scope of its
 * name: scopeActive() becomes active() with #[Scope], protected, so that a static call on the model still reaches
 * the scope through __callStatic. The calls of the scope, ->active() on a query, stay as they are.
 *
 * Reported and left: a static or private method, a class that declares a method of the new name already, and one
 * that calls the method by its old name itself. A call by the old name in another file is not seen, and neither is
 * a call on the model the new visibility refuses, so every fix is risky.
 */
#[RuleInfo(
	'laravel/scope-attribute-for-scope-prefix',
	Stage::Structure,
	description: 'Marks a local scope of a model by the attribute `Scope` instead of the prefix `scope` of its name',
	group: RuleGroup::Modernization,
	requires: ['laravel/framework' => '>=12.4'],
)]
final class ScopeAttributeForScopePrefixRule extends NodeRule
{
	private const Model = Illuminate\Database\Eloquent\Model::class;
	private const Scope = Illuminate\Database\Eloquent\Attributes\Scope::class;


	public function getVisitedTypes(): array
	{
		return [ClassNode::class];
	}


	public function enter(Node|Token $node, RuleContext $context): void
	{
		if (!$node instanceof ClassNode || !self::isModel($node, $context)) {
			return;
		}

		foreach ($node->members as $method) {
			if ($method instanceof MethodNode && preg_match('~^scope([A-Z]\w*)$~', $method->name->text, $m)) {
				self::convert($node, $method, lcfirst($m[1]), $context);
			}
		}
	}


	private static function convert(ClassNode $class, MethodNode $method, string $name, RuleContext $context): void
	{
		$old = $method->name->text;
		$message = "Method `$old()` is replaced by the method `$name()` with the attribute `#[" . self::Scope . ']`';
		$refusal = match (true) {
			$method->modifiers->isStatic() => ', but it is static',
			$method->modifiers->visibility === Visibility::Private => ', but it is private',
			array_any($class->members->getItems(), fn($member) => $member instanceof MethodNode && strcasecmp($member->name->text, $name) === 0)
				=> ", but the class declares `$name()` already",
			$class->findFirst(MethodCallNode::class, fn(MethodCallNode $call) => self::isNamed($call, $old)) !== null,
			$class->findFirst(StaticMethodCallNode::class, fn(StaticMethodCallNode $call) => self::isNamed($call, $old)) !== null
				=> ', but the class calls it by that name',
			default => null,
		};
		if ($refusal !== null) {
			$context->report($method->name, $message . $refusal, fixable: false);
			return;
		} elseif (!$context->report(
			$method->name,
			$message,
			risky: Risk::BehaviorChanges,
			because: 'a call by the old name in another file is not seen',
		)) {
			return;
		}

		$method->name->text = $name;
		$public = $method->modifiers->findToken(Token::Public);
		if ($public !== null) {
			$protected = new Token(Token::Protected, 'protected');
			$protected->setLeadingTrivia($public->leadingTrivia);
			$protected->setTrailingTrivia($public->trailingTrivia);
			$method->modifiers->replaceChild($public, $protected);
		} elseif (!($method->modifiers->visibility === Visibility::Protected)) {
			$protected = new Token(Token::Protected, 'protected');
			$protected->setLeadingTrivia($method->functionKeyword->leadingTrivia);
			$protected->setTrailingTrivia([new Trivia(Trivia::Whitespace, ' ')]);
			$method->functionKeyword->setLeadingTrivia([]);
			$method->modifiers->append($protected);
		}

		CodeWriter::addAttributes($method, $method->attributes, [CodeWriter::spellClass(self::Scope, $method, $context)], $context);
	}


	private static function isNamed(MethodCallNode|StaticMethodCallNode $call, string $name): bool
	{
		return $call->name instanceof IdentifierNode && strcasecmp($call->name->text, $name) === 0;
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
