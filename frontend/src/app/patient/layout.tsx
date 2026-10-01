import { PatientAuthProvider } from '@/lib/patient-auth-context';

export default function PatientRootLayout({ children }: { children: React.ReactNode }) {
  return <PatientAuthProvider>{children}</PatientAuthProvider>;
}
