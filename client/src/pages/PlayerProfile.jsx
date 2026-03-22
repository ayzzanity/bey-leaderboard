import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Card, Col, Row, Statistic, Tag, Table, Typography } from 'antd';

import { getPlayerProfile } from '../api/api';

export default function PlayerProfile() {
  const { id } = useParams();
  const [profile, setProfile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (id) {
      loadProfile(parseInt(id));
    }
  }, [id]);

  const loadProfile = async (playerId) => {
    setLoading(true);
    setError(null);

    try {
      const data = await getPlayerProfile(playerId);
      setProfile(data);
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to load player profile');
    } finally {
      setLoading(false);
    }
  };

  const summary = profile?.summary ?? {};

  return (
    <div className="container flex flex-col gap-6">
      <Card loading={loading} title={profile?.name ?? 'Player Profile'}>
        {error && <Typography.Paragraph type="danger">{error}</Typography.Paragraph>}

        <Row gutter={[16, 16]}>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Rank" value={summary.rank ?? 'Unranked'} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Total Points" value={summary.total_points ?? 0} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Tournaments Joined" value={summary.tournaments_joined ?? 0} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Championships" value={summary.championships ?? 0} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Swiss Kings" value={summary.swiss_kings ?? 0} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Finishers" value={summary.finishers ?? 0} />
          </Col>
          <Col xs={24} md={12} lg={8}>
            <Statistic title="Birdie Kings" value={summary.birdie_kings ?? 0} />
          </Col>
        </Row>

        {(profile?.known_aliases?.length ?? 0) > 0 && (
          <div className="mt-4">
            <Typography.Title level={5}>Known Aliases</Typography.Title>
            <div className="flex flex-wrap gap-2">
              {profile.known_aliases.map((alias) => (
                <Tag key={alias}>{alias}</Tag>
              ))}
            </div>
          </div>
        )}
      </Card>

      <Card title="Tournaments Joined">
        <Table
          dataSource={profile?.tournaments_joined ?? []}
          rowKey="tournament_id"
          columns={[
            {
              title: 'Tournament',
              render: (_, record) => <Link to={`/tournaments/${record.tournament_id}`}>{record.tournament_name}</Link>
            },
            { title: 'Date', dataIndex: 'date' },
            { title: 'Rank', dataIndex: 'rank_label' },
            { title: 'Points', dataIndex: 'points_awarded' }
          ]}
          pagination={{ pageSize: 20 }}
        />
      </Card>
    </div>
  );
}
