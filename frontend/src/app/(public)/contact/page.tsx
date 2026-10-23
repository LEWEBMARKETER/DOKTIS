import { Card } from '@/components/ui';

export default function ContactPage() {
  return (
    <div className="mx-auto max-w-xl">
      <h1 className="mb-2 text-2xl font-bold text-doktis-anthracite">Contact</h1>
      <p className="mb-6 text-sm text-slate-500">
        Vous êtes une structure sanitaire ou un professionnel de santé et souhaitez rejoindre DOKTIS ? Contactez-nous.
      </p>
      <Card>
        <ul className="space-y-2 text-sm text-slate-700">
          <li>
            <span className="font-medium">Téléphone : </span>
            <a href="tel:+24101234567" className="text-doktis-petrol hover:underline">+241 01 23 45 67</a>
          </li>
          <li>
            <span className="font-medium">Email : </span>
            <a href="mailto:contact@doktis.ga" className="text-doktis-petrol hover:underline">contact@doktis.ga</a>
          </li>
          <li>
            <span className="font-medium">WhatsApp : </span>
            <a href="https://wa.me/24101234567" target="_blank" rel="noreferrer" className="text-doktis-petrol hover:underline">
              +241 01 23 45 67
            </a>
          </li>
        </ul>
      </Card>
    </div>
  );
}
