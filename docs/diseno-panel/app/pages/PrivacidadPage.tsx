import { Shield } from "lucide-react";
import { GreenBadge } from "../components/shared";

const sections = [
  { title: "1. Responsable del tratamiento", body: "RankPro Solutions S.A. de C.V., con domicilio en Av. Paseo de la Reforma 250, Col. Juárez, Ciudad de México, C.P. 06600, es el responsable del uso y protección de sus datos personales." },
  { title: "2. Datos personales que recabamos", body: "Recabamos los siguientes datos personales: nombre completo, correo electrónico, número de teléfono, nombre de empresa, cargo, y datos de navegación (cookies, dirección IP). En ningún caso recabamos datos sensibles como datos financieros o de salud." },
  { title: "3. Finalidades del tratamiento", body: "Sus datos personales son utilizados para: (a) prestar los servicios contratados, (b) enviar comunicaciones relacionadas con nuestros servicios, (c) mejorar nuestros productos y servicios, (d) cumplir con obligaciones legales, y (e) enviar información comercial cuando haya dado su consentimiento." },
  { title: "4. Transferencia de datos", body: "RankPro no vende ni renta su información personal a terceros. Podemos compartir sus datos con proveedores de tecnología que nos apoyan en la prestación del servicio (Google, HubSpot, AWS) bajo acuerdos de confidencialidad, y únicamente con las autoridades competentes cuando así lo requiera la ley." },
  { title: "5. Derechos ARCO", body: "Usted tiene derecho de Acceder, Rectificar, Cancelar u Oponerse al tratamiento de sus datos personales (derechos ARCO). Para ejercerlos, envíe su solicitud a: privacidad@rankpro.mx con su nombre completo, copia de identificación y descripción de la solicitud. Responderemos en un plazo máximo de 20 días hábiles." },
  { title: "6. Cookies y tecnologías de rastreo", body: "Utilizamos cookies propias y de terceros para analizar el tráfico, personalizar contenido y medir el rendimiento de campañas. Puede configurar su navegador para rechazar cookies, aunque esto puede afectar la funcionalidad del sitio." },
  { title: "7. Cambios al aviso de privacidad", body: "Nos reservamos el derecho de efectuar modificaciones a este aviso. Cualquier cambio será publicado en esta página con la fecha de actualización. Le recomendamos revisarlo periódicamente." },
];

export default function PrivacidadPage() {
  return (
    <>
      <section className="bg-[#F5F7FA] py-20 px-4 border-b border-black/5">
        <div className="max-w-3xl mx-auto text-center">
          <GreenBadge><Shield size={12} />AVISO DE PRIVACIDAD</GreenBadge>
          <h1 className="text-4xl font-extrabold text-[#1A2332] mb-4">Aviso de Privacidad</h1>
          <p className="text-[#6B7280]">Última actualización: 15 de junio de 2024 · En cumplimiento con la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP)</p>
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
            <p className="text-xs text-[#6B7280]">Para cualquier consulta sobre este aviso, contáctenos en: <span className="font-semibold text-[#1A2332]">privacidad@rankpro.mx</span> · +52 55 1234 5678</p>
          </div>
        </div>
      </section>
    </>
  );
}
