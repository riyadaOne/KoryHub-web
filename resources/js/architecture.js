/*
 * Onglet Architecture (S-14, TRD §22) : Mermaid est chargé seulement quand la page en contient un
 * diagramme, en mode strict ; le texte vient du serveur (identifiants n1, n2… et libellés échappés).
 * Les clics ne passent pas par Mermaid : notre script relie chaque nœud au fichier du composant.
 */
let mermaidModule = null;
let renderCount = 0;

export async function renderArchitecture(root = document) {
    // Un diagramme n'est dessiné qu'une fois : le chargement de la page et la navigation Livewire
    // appellent tous deux ce rendu, et deux rendus simultanés du même identifiant se vident l'un l'autre.
    const containers = [...root.querySelectorAll('[data-architecture]:not([data-rendered])')];
    containers.forEach((container) => container.setAttribute('data-rendered', ''));
    if (containers.length === 0) {
        return;
    }

    mermaidModule ??= (await import('mermaid')).default;
    const dark = document.documentElement.classList.contains('dark');
    mermaidModule.initialize({ startOnLoad: false, securityLevel: 'strict', theme: dark ? 'dark' : 'neutral' });

    for (const container of containers) {
        const source = container.querySelector('[data-mermaid-source]')?.textContent ?? '';
        const target = container.querySelector('[data-mermaid-target]');
        if (! target || source === '') {
            continue;
        }

        const { svg } = await mermaidModule.render(`kory-architecture-${++renderCount}`, source);
        target.innerHTML = svg;

        const files = JSON.parse(container.dataset.files ?? '{}');
        for (const [id, path] of Object.entries(files)) {
            const node = target.querySelector(`g.node[id*="-${id}-"]`);
            if (! node) {
                continue;
            }
            const url = `${container.dataset.filesUrl}?${new URLSearchParams({ path, v: container.dataset.version })}`;
            node.setAttribute('tabindex', '0');
            node.setAttribute('role', 'link');
            node.style.cursor = 'pointer';
            node.addEventListener('click', () => window.location.assign(url));
            node.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.assign(url);
                }
            });
        }
    }
}
