import { FileText } from "lucide-react";
import { GreenBadge } from "../components/shared";

const sections = [
  { title: "1. Aceptación de los términos", body: "Al acceder y utilizar los servicios de RankPro Solutions S.A. de C.V. ('RankPro'), usted acepta estar sujeto a estos Términos de Uso. Si no está de acuerdo con alguno de estos términos, le pedimos que no utilice nuestros servicios." },
  { title: "2. Descripción de los servicios", body: "RankPro ofrece servicios de marketing digital, incluyendo SEO, campañas de publicidad pagada, desarrollo web, gestión de redes sociales y acceso a plataformas SaaS propias. Los detalles específicos de cada servicio se establecen en el contrato de servicios individual." },
  { title: "3. Propiedad intelectual", body: "Todos los contenidos del sitio web de RankPro — incluyendo textos, imágenes, logotipos, código fuente y materiales de marketing — son propiedad exclusiva de RankPro y están protegidos por las leyes de derechos de autor mexicanas e internacionales. Queda prohibida su reproducción sin autorización expresa por escrito." },
  { title: "4. Obligaciones del cliente", body: "El cliente se compromete a: (a) proporcionar información veraz y actualizada, (b) facilitar los accesos necesarios para la prestación del servicio, (c) pagar las facturas en las fechas acordadas, y (d) no utilizar los servicios para fines ilegales o que violen derechos de terceros." },
  { title: "5. Limitación de responsabilidad", body: "RankPro no garantiza resultados específicos en posicionamiento o ventas, ya que estos dependen de factores externos como cambios en algoritmos de Google, mercado y competencia. Nuestra responsabilidad se limita al monto pagado por los servicios en el mes en cuestión." },
  { title: "6. Facturación y pagos", body: "Los servicios se facturan mensualmente por adelantado. El cliente tiene 5 días hábiles para reportar discrepancias en la factura. El incumplimiento de pago por más de 15 días hábiles puede resultar en la suspensión del servicio sin responsabilidad para RankPro." },
  { title: "7. Cancelación del servicio", body: "Cualquiera de las partes puede dar por terminada la relación de servicios con 30 días de anticipación por escrito. RankPro se reserva el derecho de cancelar de forma inmediata en caso de incumplimiento grave por parte del cliente." },
  { title: "8. Ley aplicable", body: "Estos términos se rigen por las leyes de los Estados Unidos Mexicanos. Para cualquier controversia derivada de estos términos, las partes se someten a la jurisdicción de los tribunales competentes de la Ciudad de México, renunciando a cualquier otro fuero que pudiera corresponderles." },
];

export default function TerminosPage() {
  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-3xl mx-auto text-center">
          <GreenBadge><FileText size={12} />TÉRMINOS LEGALES</GreenBadge>
          <h1 className="text-4xl font-extrabold text-[#1A2332] mb-4">Términos de Uso</h1>
          <p className="text-[#6B7280]">Última actualización: 15 de junio de 2024 · Por favor lea estos términos detenidamente antes de utilizar nuestros servicios.</p>
        </div>
      </section>

      <section className="py-16 px-4 bg-white">
        <div className="max-w-3xl mx-auto space-y-8">
          {sections.map(s => (
            <div key={s.title}>
              <h2 className="text-lg font-extrabold text-[#1A2332] mb-3">{s.title}</h2>
              <p className="text-[#6B7280] leading-relaxed text-sm">{s.body}</p>
            </div>
          ))}
          <div className="pt-4 border-t border-black/5">
            <p className="text-xs text-[#6B7280]">Para consultas legales: <span className="font-semibold text-[#1A2332]">legal@rankpro.mx</span> · +52 55 1234 5678</p>
          </div>
        </div>
      </section>
    </>
  );
}
