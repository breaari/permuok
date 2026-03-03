export function getLastPayment() {
  const raw = localStorage.getItem("permuok_last_payment");
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function setLastPayment(p) {
  localStorage.setItem("permuok_last_payment", JSON.stringify(p));
}

export function clearLastPayment() {
  localStorage.removeItem("permuok_last_payment");
}