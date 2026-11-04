/** Données structurées schema.org, lues par Google et les assistants IA. */
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return (
    <script
      type="application/ld+json"
      // Le JSON est produit par nous ; on neutralise « < » pour éviter toute fermeture de balise.
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, '\\u003c') }}
    />
  );
}
