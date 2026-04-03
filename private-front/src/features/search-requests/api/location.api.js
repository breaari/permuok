import { api, unwrap } from "../../../api/http";

export async function getProvinces() {
  const res = await api.get("/locations/provinces");
  const data = unwrap(res);
  return data?.items || [];
}