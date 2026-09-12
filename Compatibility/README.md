# Third-Party Extension Compatibility Shims

This folder contains compatibility shims for specific third-party phpBB extensions with known interaction issues. Each file targets one third-party extension and is independently loadable/removable without affecting core SEO Framework functionality.

## Supported Extensions

### RecentTopics (`RecentTopicsCompatibility.php`)
- **Target Extensions**: `paybas/recenttopics` and `avathar/recenttopics`
- **Events**: `avathar.recenttopics.modify_tpl_ary`, `paybas.recenttopics.modify_tpl_ary`
- **Description**: Normalizes malformed query string concatenation where `&view=unread#unread` or `&p=...` is naively appended directly onto slash-terminated SEO permalinks, converting the first delimiter to a proper `?` query indicator.