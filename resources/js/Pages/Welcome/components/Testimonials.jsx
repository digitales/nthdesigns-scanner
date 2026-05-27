export default function Testimonials() {
  const items = [
    {
      quote: "The report landed in our inbox on a Friday. By the following Tuesday three of the four critical issues were fixed by my web developer for under £400.",
      who: "S.K.",
      role: "Practice Manager · UK Dental Group (Birmingham)",
    },
    {
      quote: "I'd had two SEO audits the year before. Neither flagged that our booking form was unreachable by keyboard. nthdesigns did, in plain English.",
      who: "J.H.",
      role: "Senior Partner · Solicitors firm (Manchester)",
    },
    {
      quote: "The comparison against Smile Studio was the bit that hurt. We doubled our profile photos in a fortnight; bookings rose 14% the month after.",
      who: "R.W.",
      role: "Owner · Independent dental practice (Solihull)",
    },
  ];
  return (
    <section className="s">
      <div className="section-head">
        <div>
          <span className="eyebrow">From the work</span>
          <h2 className="heading">What past audits have actually changed.</h2>
        </div>
        <p className="note">
          We don't keep public case studies — most of our clients are conservative
          professional firms. These are notes from operators who agreed to be quoted, lightly
          anonymised.
        </p>
      </div>

      <div className="testimonials">
        {items.map((t, i) => (
          <div key={i} className="tcard">
            <div className="open-quote">"</div>
            <div className="quote">{t.quote}</div>
            <div className="who">
              <div className="avatar">{t.who.split(".").join("")}</div>
              <div className="who-meta">
                <b>{t.who}</b>
                <div className="role">{t.role}</div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
