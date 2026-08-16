# Instructions Codex

Lire `CODEX_HANDOFF.md` en entier avant toute exploration, modification, test ou mise a jour cPanel.

Toujours commencer par:

```bash
git status --short --branch
git diff --stat
```

Ne jamais annuler les changements non commits, ne jamais exposer `.env` ou des mots de passe, et ne jamais confondre l'etat local avec l'etat actuellement deploye.
