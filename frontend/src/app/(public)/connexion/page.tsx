import Link from 'next/link';
import { Card } from '@/components/ui';

export default function ConnexionPage() {
  return (
    <div className="mx-auto max-w-md">
      <h1 className="mb-6 text-center text-2xl font-bold text-doktis-anthracite">Connexion</h1>
      <div className="flex flex-col gap-4">
        <Link href="/patient/login">
          <Card className="transition-shadow hover:shadow-md">
            <p className="font-semibold text-doktis-petrol">Espace patient</p>
            <p className="mt-1 text-sm text-slate-500">Suivez vos rendez-vous et votre dossier dans vos cabinets.</p>
          </Card>
        </Link>
        <Link href="/partenaire/connexion">
          <Card className="transition-shadow hover:shadow-md">
            <p className="font-semibold text-doktis-petrol">Espace partenaire</p>
            <p className="mt-1 text-sm text-slate-500">Gérez votre fiche, vos demandes de rendez-vous et vos statistiques.</p>
          </Card>
        </Link>
        {/* DOKTIS Office n'est volontairement pas proposé ici : le module
            SaaS complet n'est pas exposé au public pendant la phase
            Directory-first (mission Partie A.D). La route /office/login
            reste joignable directement par qui la connaît déjà. */}
      </div>
    </div>
  );
}
