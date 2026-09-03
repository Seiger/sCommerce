# sCommerce documentation

The `docs/<locale>/` tree is the canonical documentation for the code in this Git branch.
Both dDocs and the public documentation site consume these same Markdown files.

dDocs reads the installed Composer package's `docs/` directory and its `docs.json`
metadata. The installed package determines the documentation version.

The public Docusaurus implementation lives on the separate `docs` Git branch.
Its `docs.config.json` declares supported major branches and the current line.
It checks out only `docs/` from each configured branch at build time; generated
build views are disposable and are never another documentation source.

Edit documentation alongside the corresponding code change. Keep relative Markdown
links and shared assets inside this tree so both renderers can resolve them. Use
ordinary Markdown, not React/MDX components, for shared pages. Preserve `docs.json`
and the localized entrypoint filenames when reorganizing content.

Do not add Node dependencies, site themes, Docusaurus snapshots, or a second set
of translations here. See the README on the `docs` branch for local builds and
publishing instructions.
