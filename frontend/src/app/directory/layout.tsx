import Link from 'next/link';

export default function DirectoryLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
          <Link href="/directory" className="text-lg font-bold text-teal-700">
            DOKTA <span className="font-normal text-slate-400">Directory</span>
          </Link>
          <Link href="/login" className="text-sm font-medium text-slate-600 hover:text-teal-700">
            Espace cabinet
          </Link>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
    </div>
  );
}
