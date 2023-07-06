# Contributing to Embed Privacy

First, we’re happy to have you here! Thank you for taking time to contribute. :tada: :heart:

## Language

Even if we are based in Germany and thus speak German as well, please use English primarily in discussions on the Embed Privacy GitHub project. Use German only if you struggle to find the correct words in English.

## Creating issues

Before creating a new issue, please search for existing issues targeting the same problem.

Please use the available issue templates for bugs and feature requests and give as much information as possible.

## Adding code

If you want to contribute code, please create an issue first so that we can discuss if the functionality you want to implement is intended to be a reasonable value for Embed Privacy.

### Code style

Basically we use the default WordPress coding style with some small adjustments. We recommend using the [WordPress Coding Standard for PHP Code Sniffer](https://github.com/WordPress/WordPress-Coding-Standards) and exclude the following rules:

```xml
<rule ref="WordPress">
    <exclude name="WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned"/>
    <exclude name="WordPress.DB.SlowDBQuery.slow_db_query_meta_key"/>
    <exclude name="WordPress.DB.SlowDBQuery.slow_db_query_meta_value"/>
    <exclude name="WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound"/>
    <exclude name="WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound"/>
    <exclude name="WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound"/>
    <exclude name="WordPress.PHP.DisallowShortTernary.Found"/>
    <exclude name="WordPress.PHP.YodaConditions.NotYoda"/>
    <exclude name="WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound"/>
    <exclude name="WordPress.NamingConventions.PrefixAllGlobals.ShortPrefixPassed"/>
    <exclude name="WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed"/>
    <exclude name="WordPress.WhiteSpace.PrecisionAlignment.Found"/>
    <exclude name="WordPress.WP.EnqueuedResourceParameters.NotInFooter"/>
</rule>
```

### Testing

Please make sure that your code is tested in a clean environment. A huge plus will be to know the code is tested in both a single and a multisite installation.

### Commit message

We use [gitmoji](https://gitmoji.dev) to get a decent overview over what’s the purpose of a single commit. Thus, you should use them as well.
