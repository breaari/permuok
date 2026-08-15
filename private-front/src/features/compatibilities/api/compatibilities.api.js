import { http, unwrap } from "../../../api/http";

export async function getCompatibilityRecommendations(
  filters = {},
) {
  const params = {
    page: filters.page || 1,
    limit: filters.limit || 12,
    view: filters.view || "active",
    match_level:
      filters.match_level || undefined,
    min_score:
      filters.min_score || undefined,
    pending:
      filters.pending || undefined,
  };

  const res = await http.get(
    "/compatibilities/recommendations",
    {
      params,
    },
  );

  return unwrap(res);
}

export async function getCompatibilityDetail(id) {
  const res = await http.get(
    `/compatibilities/${id}`,
  );

  return unwrap(res);
}

export async function respondToCompatibility(
  id,
  response,
) {
  const res = await http.post(
    `/compatibilities/${id}/respond`,
    {
      response,
    },
  );

  return unwrap(res);
}

export async function saveCompatibilityFeedback(
  id,
  payload,
) {
  const res = await http.post(
    `/compatibilities/${id}/feedback`,
    payload,
  );

  return unwrap(res);
}